# JBlockBadWords

Version: 0.1.2 (experimental)

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

## Build

From the repository root:

```bash
./build.sh
```

This generates:

`plg_system_jblockbadwords-<version>.zip`

## Install

1. Build the plugin package:

```bash
./build.sh
```

2. In Joomla Admin, go to `System -> Install -> Extensions`.
3. Upload `plg_system_jblockbadwords-<version>.zip`.
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

## Support the continued development of JBlockBadWords

Help support the continued development of JBlockBadWords

<form action="https://www.paypal.com/donate" method="post" target="_top">
<input type="hidden" name="hosted_button_id" value="B9CLB4VNPBZZC" />
<input type="image" src="https://www.paypalobjects.com/en_US/GB/i/btn/btn_donateCC_LG.gif" border="0" name="submit" title="PayPal - The safer, easier way to pay online!" alt="Donate with PayPal button" />
<img alt="" border="0" src="https://www.paypal.com/en_GB/i/scr/pixel.gif" width="1" height="1" />
</form>