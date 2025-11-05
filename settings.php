<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Settings definitions for the local_alternatename plugin.
 *
 * @package   local_alternatename
 * @copyright 2024 Pavel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $categoryname = 'local_alternatename';

    $ADMIN->add('localplugins', new admin_category(
        $categoryname,
        get_string('pluginname', 'local_alternatename')
    ));

    $settings = new admin_settingpage(
        'local_alternatename_settings',
        get_string('settingspagetitle', 'local_alternatename')
    );

    $placeholders = '{alternatename}, {firstname}, {lastname}, {middlename}, {title}, '
        . '{prefix}, {suffix}, {username}, {email}, {idnumber}, {fullname}';

    $settings->add(new admin_setting_configtext(
        'local_alternatename/fullnamedisplay_template',
        get_string('setting_fullname_label', 'local_alternatename'),
        get_string('setting_fullname_desc', 'local_alternatename', $placeholders),
        '{firstname} {lastname}'
    ));

    $settings->add(new admin_setting_configtext(
        'local_alternatename/alternativefullname_template',
        get_string('setting_alternative_label', 'local_alternatename'),
        get_string('setting_alternative_desc', 'local_alternatename', $placeholders),
        '{alternatename} ({firstname} {lastname})'
    ));

    $ADMIN->add($categoryname, $settings);
}
