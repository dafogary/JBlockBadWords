# JBlockBadWords

Version: 0.1.0 (experimental)

JBlockBadWords is a Joomla system plugin that blocks submissions when configured bad words are found.

It checks:
- Joomla content save events (articles/pages via `com_content`)
- Kunena forum post/reply submissions (`com_kunena` POST requests)

## Plugin Location

`plugins/system/jblockbadwords`

## Features

- Admin-configurable blocked words list (comma or line separated)
- Case-sensitive or case-insensitive matching
- Substring matching or whole-word matching
- Separate toggles for Joomla content saves and Kunena post submissions

## Install

1. Build a zip package from the `plugins` folder content:

```bash
cd /workspaces/JBlockBadWords
zip -r plg_system_jblockbadwords-0.1.0-experimental.zip plugins/system/jblockbadwords
```

2. In Joomla Admin, go to `System -> Install -> Extensions`.
3. Upload `plg_system_jblockbadwords-0.1.0-experimental.zip`.
4. Go to `System -> Plugins`, find `System - JBlock Bad Words`, enable it.

## Configure

In plugin settings:

1. Fill `Blocked words` with one word per line or comma-separated.
2. Choose matching style:
	- `Case-sensitive matching`
	- `Match as substring`
3. Enable checks:
	- `Block Joomla content saves`
	- `Block Kunena post submissions`

## Notes

- For Kunena, blocking is applied to likely message fields in POST payload (`subject`, `title`, `name`, `message`, `text`, `body`, `content`).
- If a blocked word is detected, submission is stopped and an error message is shown to the user.