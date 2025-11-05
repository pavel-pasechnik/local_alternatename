# Full Name Format Plugin: local_alternatename

[![Moodle](https://img.shields.io/badge/Moodle--4.5+-orange?logo=moodle&style=flat-square)](https://moodle.org/plugins/local_alternatename)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg?style=flat-square)](https://www.gnu.org/licenses/gpl-3.0)
[![Latest Release](https://img.shields.io/github/v/release/pavel-pasechnik/local_alternatename?label=Release&style=flat-square)](https://github.com/pavel-pasechnik/local_alternatename/releases/latest)
[![Coding Style](https://img.shields.io/badge/Coding%20Style-Moodle-blueviolet?style=flat-square)](https://moodledev.io/general/development/policies/codingstyle)

**Component:** `local_alternatename`  
**Type:** Local plugin for Moodle  
**Maintainer:** Pavel Pasechnik (Kyiv, Ukraine)  
**License:** GNU GPL v3

---

## What It Does
- Allows administrators to configure the display format of user full names in Moodle using customizable templates with placeholders (e.g. `{alternatename} ({firstname} {lastname})`).  
- Supports all standard profile fields such as `{firstname}`, `{lastname}`, `{alternatename}`, `{middlename}`, `{prefix}`, `{suffix}`, `{username}`, `{email}`, `{idnumber}`, `{fullname}`, and more.  
- Automatically removes empty placeholders and surrounding punctuation when rendering the name.

---

## Installation
1. Copy this directory to `local/alternatename` in your Moodle codebase.  
2. Run the Moodle upgrade (`php admin/cli/upgrade.php`) to install the plugin.

---

## Configuration
- Set name format templates via **Site administration → Users → Accounts → Default preferences** or through the plugin’s settings page.  
- Templates can be customized per context to control how user names appear throughout the site.

---

## Usage
- Example templates:  
  - `{alternatename} ({firstname} {lastname})`  
  - `{prefix} {firstname} {lastname} {suffix}`  
- Placeholders will be replaced with user profile data, and any empty fields will be omitted cleanly.

---

## Features
- Supports all standard Moodle user profile fields as placeholders.  
- Cleans output by removing empty placeholders and adjacent punctuation.  
- Intelligent cleanup of brackets, quotes, and punctuation when placeholders are empty.  
- Compatible with Moodle versions 4.0 through 4.5.

---

## Development Notes
- Main files include `lib.php` and `settings.php`.  
- Core functionality implemented in `local_alternatename_core_user_get_fullname()` which overrides the default fullname retrieval.  
- Advanced name rendering logic in `local_alternatename_render_from_template()` handles nested and partial placeholders gracefully.

---

## License
- This plugin is licensed under the GNU General Public License v3 (GPLv3).

---

© 2025 Pavel Pasechnik
