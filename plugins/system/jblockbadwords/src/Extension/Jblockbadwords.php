<?php

declare(strict_types=1);

namespace Joomla\Plugin\System\JBlockBadWords\Extension;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\TableInterface;
use Joomla\CMS\Language\Text;

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

        $message = Text::sprintf('PLG_SYSTEM_JBLOCKBADWORDS_ERROR_BLOCKED_WORDS', implode(', ', $foundWords));
        $returnUrl = $input->server->getString('HTTP_REFERER', Route::_('index.php', false));

        $app->redirect($returnUrl, $message, 'error');
        $app->close();
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

            return str_contains(mb_strtolower($text), mb_strtolower($word));
        }

        $pattern = '/\\b' . preg_quote($word, '/') . '\\b/' . ($caseSensitive ? 'u' : 'iu');

        return (bool) preg_match($pattern, $text);
    }
}
