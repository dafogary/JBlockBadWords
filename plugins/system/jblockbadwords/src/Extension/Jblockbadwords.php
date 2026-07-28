<?php

declare(strict_types=1);

namespace Joomla\Plugin\System\JBlockBadWords\Extension;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\TableInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;

final class Jblockbadwords extends CMSPlugin
{
    protected $autoloadLanguage = true;

    public function onContentBeforeSave($context, $table, $isNew, $data = []): bool
    {
        if (!(bool) $this->params->get('check_article_save', 1)) {
            return true;
        }

        if (!$table instanceof TableInterface) {
            return true;
        }

        if (!$this->shouldCheckContentContext((string) $context)) {
            return true;
        }

        $textToCheck = $this->collectContentText($table, $data);

        if ($textToCheck === '') {
            return true;
        }

        $foundWords = $this->findBlockedWords($textToCheck);

        if ($foundWords === []) {
            return true;
        }

        $app = Factory::getApplication();
        $this->handleBlockedAttempt($foundWords);

        $message = Text::sprintf('PLG_SYSTEM_JBLOCKBADWORDS_ERROR_BLOCKED_WORDS', implode(', ', $foundWords));

        $app->enqueueMessage($message, 'error');

        return false;
    }

    public function onAfterRoute(): void
    {
        if (!(bool) $this->params->get('check_kunena_post', 1)) {
            return;
        }

        $app = Factory::getApplication();

        if (!$app->isClient('site')) {
            return;
        }

        $input = $app->input;

        if (strtoupper($input->getMethod()) !== 'POST') {
            return;
        }

        if ($input->getCmd('option') !== 'com_kunena') {
            return;
        }

        if (!$this->isLikelyKunenaSubmitTask($input->getCmd('task'))) {
            return;
        }

        $textToCheck = $this->collectKunenaText();

        if ($textToCheck === '') {
            return;
        }

        $foundWords = $this->findBlockedWords($textToCheck);

        if ($foundWords === []) {
            return;
        }

        $this->handleBlockedAttempt($foundWords);

        $message = Text::sprintf('PLG_SYSTEM_JBLOCKBADWORDS_ERROR_BLOCKED_WORDS', implode(', ', $foundWords));
        $returnUrl = $input->server->getString('HTTP_REFERER', Route::_('index.php', false));

        $app->enqueueMessage($message, 'error');
        $app->redirect($returnUrl);
        $app->close();
    }

    private function handleBlockedAttempt(array $foundWords): void
    {
        $details = $this->buildAttemptDetails($foundWords);

        $this->logBlockedAttempt($details);
        $this->sendBlockedAttemptEmail($details);
    }

    private function buildAttemptDetails(array $foundWords): array
    {
        $app = Factory::getApplication();
        $input = $app->input;
        $user = $app->getIdentity();

        return [
            'username' => $user !== null && !empty($user->username) ? (string) $user->username : 'guest',
            'userId' => $user !== null ? (int) $user->id : 0,
            'ip' => (string) $input->server->getString('REMOTE_ADDR', 'unknown'),
            'blockedWords' => implode(', ', $foundWords),
            'url' => Uri::getInstance()->toString(),
            'timestamp' => Factory::getDate()->toSql(),
        ];
    }

    private function logBlockedAttempt(array $details): void
    {
        static $loggerRegistered = false;

        if (!$loggerRegistered) {
            Log::addLogger(
                [
                    'text_file' => 'plg_system_jblockbadwords.php',
                ],
                Log::ALL,
                ['plg_system_jblockbadwords']
            );

            $loggerRegistered = true;
        }

        Log::add(
            sprintf(
                'Blocked submission detected. Username: %s | User ID: %d | IP: %s | Words: %s | URL: %s | Time: %s',
                $details['username'],
                $details['userId'],
                $details['ip'],
                $details['blockedWords'],
                $details['url'],
                $details['timestamp']
            ),
            Log::WARNING,
            'plg_system_jblockbadwords'
        );
    }

    private function sendBlockedAttemptEmail(array $details): void
    {
        try {
            $config = Factory::getConfig();
            $defaultRecipient = trim((string) $config->get('mailfrom', ''));
            $recipients = $this->getNotificationRecipients($defaultRecipient);

            if ($recipients === []) {
                return;
            }

            $siteName = (string) $config->get('sitename', 'Joomla Site');
            $mailFrom = $defaultRecipient;
            $fromName = (string) $config->get('fromname', $siteName);

            if ($mailFrom === '') {
                return;
            }

            $mailer = Factory::getMailer();
            $mailer->setSender([$mailFrom, $fromName]);
            $mailer->addRecipient($recipients);
            $mailer->setSubject(Text::sprintf('PLG_SYSTEM_JBLOCKBADWORDS_EMAIL_SUBJECT', $siteName));
            $mailer->setBody(
                Text::sprintf(
                    'PLG_SYSTEM_JBLOCKBADWORDS_EMAIL_BODY',
                    $details['username'],
                    (string) $details['userId'],
                    $details['ip'],
                    $details['blockedWords'],
                    $details['url'],
                    $details['timestamp']
                )
            );
            $mailer->isHtml(false);
            $mailer->send();
        } catch (\Throwable $e) {
            Log::add(
                'Unable to send blocked-attempt email notification: ' . $e->getMessage(),
                Log::WARNING,
                'plg_system_jblockbadwords'
            );
        }
    }

    private function getNotificationRecipients(string $defaultRecipient): array
    {
        $configured = (string) $this->params->get('notification_emails', '');
        $source = trim($configured) !== '' ? $configured : $defaultRecipient;

        if (trim($source) === '') {
            return [];
        }

        $chunks = preg_split('/[\r\n,;]+/', $source) ?: [];
        $emails = [];

        foreach ($chunks as $chunk) {
            $email = trim($chunk);

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    private function shouldCheckContentContext(string $context): bool
    {
        $allowedContexts = [
            'com_content.article',
            'com_content.form',
            'com_content.category',
        ];

        return in_array($context, $allowedContexts, true);
    }

    private function collectContentText(TableInterface $table, array $data): string
    {
        $parts = [
            (string) ($data['title'] ?? ''),
            (string) ($data['introtext'] ?? ''),
            (string) ($data['fulltext'] ?? ''),
        ];

        if ($parts[0] === '' && property_exists($table, 'title')) {
            $parts[0] = (string) ($table->title ?? '');
        }

        if ($parts[1] === '' && property_exists($table, 'introtext')) {
            $parts[1] = (string) ($table->introtext ?? '');
        }

        if ($parts[2] === '' && property_exists($table, 'fulltext')) {
            $parts[2] = (string) ($table->fulltext ?? '');
        }

        return implode("\n", array_filter($parts, static fn (string $value): bool => $value !== ''));
    }

    private function collectKunenaText(): string
    {
        $input = Factory::getApplication()->input;

        $parts = [
            (string) $input->post->get('subject', '', 'raw'),
            (string) $input->post->get('title', '', 'raw'),
            (string) $input->post->get('name', '', 'raw'),
            (string) $input->post->get('message', '', 'raw'),
            (string) $input->post->get('text', '', 'raw'),
            (string) $input->post->get('body', '', 'raw'),
            (string) $input->post->get('content', '', 'raw'),
        ];

        return implode("\n", array_filter($parts, static fn (string $value): bool => trim($value) !== ''));
    }

    private function isLikelyKunenaSubmitTask(string $task): bool
    {
        if ($task === '') {
            return true;
        }

        $task = strtolower($task);

        foreach (['save', 'post', 'reply', 'create', 'submit'] as $needle) {
            if (str_contains($task, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function getBlockedWords(): array
    {
        $raw = (string) $this->params->get('blocked_words', '');

        if (trim($raw) === '') {
            return [];
        }

        $chunks = preg_split('/[\r\n,]+/', $raw) ?: [];
        $words = [];

        foreach ($chunks as $chunk) {
            $word = trim($chunk);

            if ($word !== '') {
                $words[] = $word;
            }
        }

        return array_values(array_unique($words));
    }

    private function findBlockedWords(string $text): array
    {
        $blockedWords = $this->getBlockedWords();

        if ($blockedWords === []) {
            return [];
        }

        $caseSensitive = (bool) $this->params->get('case_sensitive', 0);
        $matchSubstring = (bool) $this->params->get('match_substring', 1);
        $hits = [];

        foreach ($blockedWords as $word) {
            if ($this->containsWord($text, $word, $caseSensitive, $matchSubstring)) {
                $hits[] = $word;
            }
        }

        return $hits;
    }

    private function containsWord(string $text, string $word, bool $caseSensitive, bool $matchSubstring): bool
    {
        if ($word === '') {
            return false;
        }

        if ($matchSubstring) {
            if ($caseSensitive) {
                return str_contains($text, $word);
            }

            return str_contains($this->toLower($text), $this->toLower($word));
        }

        $pattern = '/\\b' . preg_quote($word, '/') . '\\b/' . ($caseSensitive ? 'u' : 'iu');

        return (bool) preg_match($pattern, $text);
    }

    private function toLower(string $value): string
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value);
        }

        return strtolower($value);
    }
}
