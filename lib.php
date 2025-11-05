<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Local fullname format overrides.
 *
 * @package   local_alternatename
 * @copyright 2025 Pavel Pasechnik
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns the best display name for a user using admin controlled templates.
 *
 * Supports multiple templates separated by ';' and picks the first non-empty
 * rendered result (e.g. 'alternatename;firstname lastname').
 *
 * @param \stdClass $user The user data to render.
 * @return string
 */
function local_alternatename_core_user_get_fullname(\stdClass $user) {
    global $CFG, $SESSION;

    $params = func_get_args();
    array_shift($params); // Drop the $user argument.

    $override = false;
    $options = [];
    foreach ($params as $param) {
        if (is_bool($param)) {
            $override = $param;
        } else if (is_array($param)) {
            $options = $param + $options;
        }
    }
    if (isset($options['override'])) {
        $override = (bool)$options['override'];
    }

    $user = clone($user);

    if (!isset($user->firstname) && !isset($user->lastname)) {
        return '';
    }

    if (!$override) {
        if (!empty($CFG->forcefirstname)) {
            $user->firstname = $CFG->forcefirstname;
        }
        if (!empty($CFG->forcelastname)) {
            $user->lastname = $CFG->forcelastname;
        }
        if (!empty($SESSION->fullnamedisplay)) {
            $format = $SESSION->fullnamedisplay;
        }
    }

    if (!isset($format)) {
        $setting = $override ? 'alternativealternatename' : 'fullnamedisplay';
        $format = $CFG->$setting ?? get_config('core', $setting);
    }

    if ((empty($format) || $format === 'language')) {
        // Use the language pack format.
        return get_string('fullnamedisplay', null, $user);
    }

    $templates = array_filter(array_map('trim', explode(';', $format)), 'strlen');
    if (empty($templates)) {
        $templates = [$format];
    }

    foreach ($templates as $template) {
        $display = local_alternatename_render_from_template($template, $user);
        if ($display !== '') {
            return $display;
        }
    }

    $fallback = trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? ''));
    if ($fallback !== '') {
        return $fallback;
    }

    foreach (\core_user\fields::get_name_fields() as $field) {
        if (!empty($user->$field)) {
            return $user->$field;
        }
    }

    return '';
}

/**
 * Renders the given template by replacing user name placeholders.
 *
 * @param string $template Template such as 'alternatename (firstname lastname)'.
 * @param \stdClass $user User data as provided to the fullname callback.
 * @return string Rendered display string or an empty string if nothing matches.
 */
function local_alternatename_render_from_template(string $template, \stdClass $user): string {
    $fields = \core_user\fields::get_name_fields();
    $display = $template;
    $containsplaceholders = false;

    foreach ($fields as $field) {
        if (strpos($display, $field) === false) {
            continue;
        }
        $containsplaceholders = true;
        $value = isset($user->$field) ? trim((string)$user->$field) : '';
        $display = str_replace($field, $value === '' ? 'EMPTY' : $value, $display);
    }

    if (!$containsplaceholders) {
        return trim($display);
    }

    $patterns = [
        '/[[:punct:]「」]*EMPTY[[:punct:]「」]*/u',
        '/\s{2,}/u',
    ];
    foreach ($patterns as $pattern) {
        $display = preg_replace($pattern, ' ', $display);
    }

    $display = trim(str_replace('EMPTY', '', $display));

    return $display;
}
