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
        foreach (local_alternatename_get_template_placeholders($template, $user) as $fieldname) {
            $placeholderfields[$fieldname] = true;
        }
    }
    if (!empty($placeholderfields)) {
        $hasplaceholdervalue = false;
        foreach (array_keys($placeholderfields) as $fieldname) {
            $fieldvalue = local_alternatename_get_placeholder_value($user, $fieldname);
            if ($fieldvalue !== '') {
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
            $candidate = local_alternatename_get_placeholder_value($user, $fieldname);
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
 * Returns a list of placeholder field names referenced in a template.
 *
 * @param string $template Raw template string.
 * @param \stdClass $user User record.
 * @return array<int, string> List of placeholder field names.
 */
function local_alternatename_get_template_placeholders(string $template, \stdClass $user): array {
    $placeholders = [];
    $normalized = local_alternatename_normalise_template($template, $user);
    if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $normalized, $matches)) {
        foreach ($matches[1] as $fieldname) {
            $placeholders[$fieldname] = true;
        }
    }
    return array_keys($placeholders);
}

/**
 * Normalises templates so that recognised placeholder tokens are wrapped in braces.
 *
 * Supports both {firstname} and bare tokens such as firstname or shorthand aliases (A-E).
 *
 * @param string $template Raw template string.
 * @param \stdClass $user User record.
 * @return string Template with placeholders wrapped in braces.
 */
function local_alternatename_normalise_template(string $template, \stdClass $user): string {
    $knownfields = local_alternatename_get_supported_fields($user);
    $aliases = local_alternatename_get_alias_map();

    return preg_replace_callback(
        '/\{([a-zA-Z0-9_]+)\}|(?<!\{)\b([a-zA-Z][a-zA-Z0-9_]*)\b/u',
        static function (array $matches) use ($knownfields, $aliases): string {
            $token = '';
            if (!empty($matches[1])) {
                $token = $matches[1];
            } else if (!empty($matches[2])) {
                $token = $matches[2];
            }
            if ($token === '') {
                return $matches[0];
            }
            $resolved = local_alternatename_resolve_placeholder_token($token, $knownfields, $aliases);
            if ($resolved === null) {
                return $matches[0];
            }
            return '{' . $resolved . '}';
        },
        $template
    );
}

/**
 * Returns the list of recognised placeholder field names.
 *
 * @param \stdClass $user User record.
 * @return array<int, string> Supported placeholder names.
 */
function local_alternatename_get_supported_fields(\stdClass $user): array {
    $fields = \core_user\fields::get_name_fields();
    $commonfields = [
        'alternatename',
        'alternatenameprefix',
        'fullname',
        'firstnamephonetic',
        'lastnamephonetic',
        'middlename',
        'middlenamephonetic',
        'prefix',
        'suffix',
        'username',
        'email',
        'idnumber',
    ];
    $fields = array_merge($fields, $commonfields, array_keys((array)$user));
    $fields = array_filter($fields, static function ($field): bool {
        return is_string($field) && $field !== '';
    });
    $fields = array_values(array_unique($fields));
    return $fields;
}

/**
 * Renders the given template by replacing user name placeholders.
 *
 * @param string $template Template such as 'alternatename (firstname lastname)'.
 *                         Braces around placeholders are optional.
 * @param \stdClass $user User data as provided to the fullname callback.
 * @return string Rendered display string or an empty string if nothing matches.
 */
function local_alternatename_render_from_template(string $template, \stdClass $user): string {
    $normalizedtemplate = local_alternatename_normalise_template($template, $user);
    $display = $normalizedtemplate;

    // Find all placeholders in the form {placeholder}.
    preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $display, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $placeholder = $match[0];
        $field = $match[1];
        $value = local_alternatename_get_placeholder_value($user, $field);

        if ($value !== '') {
            // Replace placeholder with the value.
            $display = str_replace($placeholder, $value, $display);
        } else {
            // Remove placeholder only (cleanup handles surrounding characters later).
            $display = str_replace($placeholder, '', $display);
        }
    }

    return local_alternatename_cleanup_display($display);
}

/**
 * Returns mapping of shorthand aliases to placeholder field names.
 *
 * @return array<string, string>
 */
function local_alternatename_get_alias_map(): array {
    return [
        'A' => 'alternatename',
        'B' => 'firstname',
        'C' => 'lastname',
        'D' => 'middlename',
        'E' => 'alternatenameprefix',
    ];
}

/**
 * Resolves a token into a supported placeholder name.
 *
 * @param string $token Raw token from template.
 * @param array<int, string> $knownfields Supported fields.
 * @param array<string, string> $aliases Alias map.
 * @return string|null Canonical placeholder name or null when unsupported.
 */
function local_alternatename_resolve_placeholder_token(string $token, array $knownfields, array $aliases): ?string {
    if ($token === '') {
        return null;
    }

    $upper = strtoupper($token);
    if (isset($aliases[$upper])) {
        return $aliases[$upper];
    }

    foreach ($knownfields as $field) {
        if (strcasecmp($field, $token) === 0) {
            return $field;
        }
    }

    return null;
}

/**
 * Returns the rendered value for a placeholder.
 *
 * @param \stdClass $user User data object.
 * @param string $fieldname Placeholder field name.
 * @return string Trimmed value or empty string when not available.
 */
function local_alternatename_get_placeholder_value(\stdClass $user, string $fieldname): string {
    $fieldname = trim($fieldname);
    if ($fieldname === '') {
        return '';
    }

    switch ($fieldname) {
        case 'alternatenameprefix':
            $alternatename = trim((string)($user->alternatename ?? ''));
            if ($alternatename === '') {
                return '';
            }
            return trim((string)($user->prefix ?? ''));
        default:
            $value = $user->$fieldname ?? '';
            return trim((string)$value);
    }
}

/**
 * Cleans up leftover punctuation, quotes, and whitespace after placeholder replacement.
 *
 * @param string $display Raw rendered value.
 * @return string Cleaned display string.
 */
function local_alternatename_cleanup_display(string $display): string {
    // Convert non-breaking spaces to regular spaces so trimming works uniformly.
    $display = preg_replace('/\x{00A0}/u', ' ', $display);

    // Normalise whitespace early.
    $display = preg_replace('/\s+/u', ' ', $display);

    // Remove empty brackets or quotes that lost all content.
    $display = preg_replace('/\(\s*\)/u', '', $display);
    $display = preg_replace('/\[\s*\]/u', '', $display);
    $display = preg_replace('/\{\s*\}/u', '', $display);
    $display = preg_replace('/"\s*"/u', '', $display);
    $display = preg_replace("/'\s*'/u", '', $display);
    $display = preg_replace('/«\s*»/u', '', $display);

    // Remove brackets that now contain only punctuation or symbols.
    $display = preg_replace('/\(\s*[^\pL\d]+\s*\)/u', '', $display);
    $display = preg_replace('/\[\s*[^\pL\d]+\s*\]/u', '', $display);
    $display = preg_replace('/\{\s*[^\pL\d]+\s*\}/u', '', $display);

    // Remove spaces before punctuation and adjust spaces near brackets.
    $display = preg_replace('/\s+([,.;:!?])/', '$1', $display);
    $display = preg_replace('/([\(\[\{«])\s+/', '$1', $display);
    $display = preg_replace('/\s+([\)\]\}»])/', '$1', $display);

    // Collapse repeated spaces and trim.
    $display = preg_replace('/\s{2,}/u', ' ', $display);
    $display = trim($display);

    if ($display === '') {
        return '';
    }

    // Drop leading separators such as ';' left behind after removing placeholders.
    $display = preg_replace('/^[;,·•\-–—]+\s*/u', '', $display);

    // Unwrap enclosing brackets or quotes when they surround the entire string.
    $display = local_alternatename_unwrap_enclosing_pairs($display);

    // Final whitespace normalisation.
    $display = preg_replace('/\s{2,}/u', ' ', $display);
    return trim($display);
}

/**
 * Removes enclosing bracket or quote pairs that wrap the entire string.
 *
 * @param string $display Display value.
 * @return string Unwrapped string.
 */
function local_alternatename_unwrap_enclosing_pairs(string $display): string {
    $pairs = [
        ['«', '»'],
        ['"', '"'],
        ["'", "'"],
        ['(', ')'],
        ['[', ']'],
        ['{', '}'],
    ];

    $result = $display;
    $changed = true;
    while ($changed && $result !== '') {
        $changed = false;
        foreach ($pairs as [$open, $close]) {
            $pattern = '/^\s*' . preg_quote($open, '/') . '(.*)' . preg_quote($close, '/') . '\s*$/u';
            if (preg_match($pattern, $result, $match)) {
                $inner = trim($match[1]);
                if ($inner === '') {
                    return '';
                }
                $result = $inner;
                $changed = true;
                break;
            }
        }
    }

    return $result;
}
