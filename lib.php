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
 * @param bool $override Whether to override forced names.
 * @param array $options Additional options.
 * @return string
 */
function local_alternatename_core_user_get_fullname(\stdClass $user, bool $override = false, array $options = []): string {
    global $CFG, $SESSION;

    if (isset($options['override'])) {
        $override = (bool)$options['override'];
    }

    $user = clone($user);

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
        $setting = $override ? 'alternativefullnameformat' : 'fullnamedisplay';
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

    // Check whether at least one placeholder referenced in the templates has data.
    $placeholderfields = [];
    foreach ($templates as $template) {
        $matches = [];
        if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $template, $matches)) {
            foreach ($matches[1] as $fieldname) {
                $placeholderfields[$fieldname] = true;
            }
        }
    }
    if (!empty($placeholderfields)) {
        $hasplaceholdervalue = false;
        foreach (array_keys($placeholderfields) as $fieldname) {
            $fieldvalue = $user->$fieldname ?? null;
            if ($fieldvalue !== null && trim((string)$fieldvalue) !== '') {
                $hasplaceholdervalue = true;
                break;
            }
        }
        if (!$hasplaceholdervalue) {
            return '';
        }
    }

    foreach ($templates as $template) {
        $display = local_alternatename_render_from_template($template, $user);
        if ($display !== '') {
            return $display;
        }
    }

    $fallback = '';
    if (!empty($placeholderfields)) {
        foreach (array_keys($placeholderfields) as $fieldname) {
            $candidate = trim((string)($user->$fieldname ?? ''));
            if ($candidate !== '') {
                if ($fallback === '') {
                    $fallback = $candidate;
                } else {
                    $fallback .= ' ' . $candidate;
                }
            }
        }
    }
    if ($fallback === '') {
        $fallback = trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? ''));
    }
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
    $display = $template;

    // Remove separators and surrounding characters if the first placeholder is empty.
    // If the first placeholder is empty, remove the characters, quotation marks, and brackets
    // that separate it from the subsequent ones.
    preg_match('/^\s*\{([a-zA-Z0-9_]+)\}/', $template, $firstmatch);
    if ($firstmatch) {
        $firstfield = $firstmatch[1];
        $firstvalue = isset($user->$firstfield) ? trim((string)$user->$firstfield) : '';
        if ($firstvalue === '') {
            // Remove the punctuation directly after the first placeholder, along with paired closing symbols later.
            $firstplaceholderpattern = '/^\s*\{\s*'
                . preg_quote($firstfield, '/')
                . '\s*\}[\s\p{P}\p{S}"\'«»\(\[\{]+/u';
            $display = preg_replace($firstplaceholderpattern, '', $display);
            // Remove paired closing symbols if there are no letters or numbers after them.
            $display = preg_replace('/["\'»\)\]\}]+\s*$/u', '', $display);
        }
    }

    // Find all placeholders in the form {placeholder}.
    preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $display, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $placeholder = $match[0];
        $field = $match[1];
        $value = isset($user->$field) ? trim((string)$user->$field) : '';

        if (trim($value) !== '') {
            // Replace placeholder with the value.
            $display = str_replace($placeholder, $value, $display);
        } else {
            // Remove placeholder and surrounding punctuation, brackets and spaces.
            // Pattern to remove punctuation, brackets and spaces around the placeholder.
            // We will replace from the placeholder outwards to remove surrounding characters.

            // Escape placeholder for regex.
            $ph = preg_quote($placeholder, '/');

            // Remove punctuation, brackets, and spaces around the placeholder.
            // For example: " ( {placeholder} ) ", " - {placeholder},", etc.
            $display = preg_replace('/[\s\p{P}\p{S}]*' . $ph . '[\s\p{P}\p{S}]*/u', '', $display);
        }
    }

    // If no placeholders remain near quotes, brackets, or punctuation, remove the surrounding or separating characters.
    if (!preg_match('/\{[a-zA-Z0-9_]+\}/', $display)) {
        $display = preg_replace('/["\'«»\(\[\{].*?["\'»\)\]\}]/u', '', $display);
        // We also remove any punctuation marks that are not followed by any data.
        $display = preg_replace('/[\p{P}\p{S}]+\s*$/u', '', $display);
    }

    // Remove any brackets that do not contain letters, numbers, or Unicode characters (left over from deleted placeholders).
    $display = preg_replace('/\(\s*[^\pL\d]+\s*\)/u', '', $display);
    $display = preg_replace('/\[\s*[^\pL\d]+\s*\]/u', '', $display);
    $display = preg_replace('/\{\s*[^\pL\d]+\s*\}/u', '', $display);

    // Remove empty parentheses and surrounding spaces if there are no characters inside.
    $display = preg_replace('/\(\s*\)/u', '', $display);
    $display = preg_replace('/\[\s*\]/u', '', $display);
    $display = preg_replace('/\{\s*\}/u', '', $display);

    // Remove spaces before punctuation.
    $display = preg_replace('/\s+([,.;:!?])/', '$1', $display);

    // Remove spaces after opening brackets and before closing brackets.
    $display = preg_replace('/([\(\[\{])\s+/', '$1', $display);
    $display = preg_replace('/\s+([\)\]\}])/', '$1', $display);

    // Remove repeated spaces and trim again after bracket cleanup.
    $display = preg_replace('/\s{2,}/u', ' ', $display);
    $display = trim($display);

    return $display;
}
