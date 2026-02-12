<?php
// Enable full error reporting for development.
error_reporting(E_ALL);
// Disable direct display of errors to the browser. Set to 1 or E_ALL for development.
ini_set('display_errors', 0); // Set to 0 in production.

// Determine if the application is in production mode based on 'display_errors' setting.
// This variable is used to conditionally show/hide specific debug information sections.
$isProduction = (ini_get('display_errors') === '0');

// Include the main password processing logic.
require_once 'PasswordHelper.php';

// Initialize variables for password analysis and generation.
$password_to_analyze = null; // The password/passphrase string that will be analyzed.
$generated_password_type = null; // Describes how the password was generated (e.g., 'Random Password', 'Passphrase').
$input_password = $_POST['input_password'] ?? ''; // User-provided password from the analysis form.
$analysis = null; // Array холдинг the results from PasswordHelper::analyzePassword.
$show_results_view = false; // Controls whether to display the input forms or the analysis results view.

// --- Generation Options Initialization ---
// Password Generation Options
$length = $_POST['length'] ?? 64; // Default length for random passwords.
// Validate and sanitize password length.
if (ctype_digit((string)$length) && (int)$length >= 10 && (int)$length <= 128) {
    $length = (int)$length;
} else {
    $length = 64; // Fallback to default if validation fails.
}

// Passphrase Generation Options
$passphrase_word_count = $_POST['passphrase_word_count'] ?? 4; // Default word count for passphrases.
// Validate and sanitize passphrase word count.
if (ctype_digit((string)$passphrase_word_count) && (int)$passphrase_word_count >= 2 && (int)$passphrase_word_count <= 10) {
    $passphrase_word_count = (int)$passphrase_word_count;
} else {
    $passphrase_word_count = 4; // Fallback to default.
}

$passphrase_separator = $_POST['passphrase_separator'] ?? '-'; // Default separator for passphrases.
// Validate passphrase separator against a list of allowed characters.
if (!in_array($passphrase_separator, ['-', '_', ' ', '.'])) {
    $passphrase_separator = '-'; // Fallback to default.
}

// Default dictionary path for passphrase generation.
$passphrase_dict = PasswordHelper::DEFAULT_DICTIONARY;

// Determine if the current request is a POST request and if the action is for passphrase generation.
// This is used to set default checkbox states for passphrase enhancements.
$is_post_request = ($_SERVER['REQUEST_METHOD'] === 'POST');
$action_is_generate_passphrase = ($is_post_request && isset($_POST['action']) && $_POST['action'] === 'generate_passphrase');

// Set passphrase enhancement options. Defaults to true on initial load or if not part of a specific passphrase generation submission.
$passphrase_capitalize = ($is_post_request && $action_is_generate_passphrase) ? isset($_POST['passphrase_capitalize_cb']) : true;
$passphrase_add_number = ($is_post_request && $action_is_generate_passphrase) ? isset($_POST['passphrase_add_number_cb']) : true;
$passphrase_add_symbol = ($is_post_request && $action_is_generate_passphrase) ? isset($_POST['passphrase_add_symbol_cb']) : true;

// Unicode character enhancement options (default: checked/true).
$action_is_generate_random = ($is_post_request && isset($_POST['action']) && $_POST['action'] === 'generate_random');
$random_add_unicode = ($is_post_request && $action_is_generate_random) ? isset($_POST['random_add_unicode_cb']) : true;
$passphrase_add_unicode = ($is_post_request && $action_is_generate_passphrase) ? isset($_POST['passphrase_add_unicode_cb']) : true;
// --- End Generation Options Initialization ---

// Get the action from the POST request, if any.
$action = $_POST['action'] ?? null;
// Initialize error message variable.
$errorMessage = null;

// Process form submissions if the request method is POST and an action is specified.
if ($is_post_request && $action) {
    if ($action === 'generate_random') {
        // Process random password generation.
        $length_val = filter_var($length, FILTER_VALIDATE_INT, ['options' => ['min_range' => 10, 'max_range' => 128, 'default' => 64]]);
        if ($length_val === false) {
            $length = 64; // Default length if invalid.
            $errorMessage = "Invalid length for password generation, using default (64).";
        } else {
            $length = $length_val;
        }
        try {
            if (!$errorMessage) {
                $password_to_analyze = PasswordHelper::generatePassword($length, true, true, true, true, $random_add_unicode);
                $generated_password_type = 'Random Password';
            }
        } catch (Exception $e) {
            $errorMessage = "Error during password generation: " . $e->getMessage();
        }
    } elseif ($action === 'generate_passphrase') {
        // Process passphrase generation.
        $word_count_val = filter_var($passphrase_word_count, FILTER_VALIDATE_INT, ['options' => ['min_range' => 2, 'max_range' => 10, 'default' => 4]]);
        if ($word_count_val === false) {
            $passphrase_word_count = 4; // Default word count if invalid.
            $errorMessage = "Invalid word count, using default (4).";
        } else {
            $passphrase_word_count = $word_count_val;
        }
        try {
            if (!$errorMessage) {
                $password_to_analyze = PasswordHelper::generatePassphrase(
                    $passphrase_word_count,
                    $passphrase_separator,
                    $passphrase_dict,
                    4, // minWordLength (hardcoded for now, could be a form option)
                    8, // maxWordLength (hardcoded for now, could be a form option)
                    $passphrase_capitalize,
                    $passphrase_add_number,
                    $passphrase_add_symbol,
                    $passphrase_add_unicode
                );
                $generated_password_type = 'Passphrase';
            }
        } catch (Exception $e) {
            $errorMessage = "Error during passphrase generation: " . $e->getMessage();
        }
    } elseif ($action === 'analyze_input') {
        // Process analysis of a user-inputted password.
        if (!empty($input_password)) {
            $password_to_analyze = $input_password;
        } else {
            $errorMessage = "Please enter a password/passphrase to analyze.";
        }
    }

    // If a password is ready for analysis and no preceding errors occurred.
    if ($password_to_analyze && !$errorMessage) {
        try {
            $analysis = PasswordHelper::analyzePassword($password_to_analyze);
            $show_results_view = true; // Switch to the results view.
        } catch (Exception $e) {
            $errorMessage = "Error during analysis: " . $e->getMessage();
            $show_results_view = false; // Stay on forms view if analysis fails.
        }
    } elseif ($errorMessage) {
        // If an error occurred before analysis (e.g., invalid input), stay on forms view.
        $show_results_view = false;
    }
}

// Handle GET requests for resetting to the form view.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['view']) && $_GET['view'] === 'form') {
    $show_results_view = false;
    // Reset variables for a clean form state.
    $input_password = '';
    $password_to_analyze = null;
    $analysis = null;
    $generated_password_type = null;
    $errorMessage = null;
}

/**
 * Generates visual elements (strength bar, recommendation text) based on analysis data.
 *
 * @param string $analyzer The name of the analyzer (e.g., 'zxcvbn', 'owasp_php').
 * @param array|null $analysisData The analysis data array for the specific password.
 * @return array An array containing 'bar' (HTML for strength bar), 'recommendation', 'level_text', and 'level_int'.
 */
function getStrengthVisuals(string $analyzer, ?array $analysisData): array {
    $level = 0; // Default strength level (Weakest).
    $recommendation = "Insufficient analysis data."; // Default recommendation message.
    $levelText = "N/A"; // Default text for the strength level.
    
    // Colors and texts for the strength bar segments.
    $barColors = ["#d9534f", "#f0ad4e", "#f0ad4e", "#5cb85c"]; // Red, Orange, Orange (for Okay/Fair), Green.
    $barTextColors = ["#fff", "#212529", "#212529", "#fff"]; // Text colors for bar segments.
    $barSegmentTexts = ["Weak", "Okay", "Good", "Strong"]; // Labels for strength levels.

    if ($analysisData) {
        // Logic for Zxcvbn analyzer.
        if ($analyzer === 'zxcvbn' && isset($analysisData['zxcvbn_full_results'])) {
            $score = $analysisData['zxcvbn_full_results']['score'] ?? -1;
            if ($score === 0) { $level = 0; $levelText = "Very Weak"; $recommendation = "Avoid (Zxcvbn score: Very Weak)"; }
            elseif ($score === 1) { $level = 1; $levelText = "Weak"; $recommendation = "Avoid (Zxcvbn score: Weak)"; }
            elseif ($score === 2) { $level = 2; $levelText = "Fair"; $recommendation = "Usable with caution (Zxcvbn score: Fair)"; }
            elseif ($score >= 3) { $level = 3; $levelText = ($score == 3 ? "Strong" : "Very Strong"); $recommendation = "Good to use (Zxcvbn score: " . $levelText . ")"; }
        } 
        // Logic for PHP-based OWASP-like policy.
        elseif ($analyzer === 'owasp_php' && isset($analysisData['owasp'])) {
            $rulesPassed = $analysisData['owasp']['rules_passed'] ?? 0;
            $isStrongByOwaspCriteria = $analysisData['owasp']['is_strong'] ?? false;
            if (!$isStrongByOwaspCriteria || $rulesPassed < 3) { $level = 0; $levelText = "Non-Compliant"; $recommendation = "Avoid (Fails basic policy)"; }
            elseif ($rulesPassed <= 3 && $isStrongByOwaspCriteria) { $level = 1; $levelText = "Passable"; $recommendation = "Passable, consider improving (Basic policy)"; }
            elseif ($rulesPassed <= 4 && $isStrongByOwaspCriteria) { $level = 2; $levelText = "Good"; $recommendation = "Good (Meets most basic criteria)"; }
            elseif ($rulesPassed >= 5 && $isStrongByOwaspCriteria) { $level = 3; $levelText = "Excellent"; $recommendation = "Excellent (Meets all basic criteria)"; }
            else { $level = 0; $levelText = "Non-Compliant"; $recommendation = "Avoid (Basic policy criteria unclear or not met)";}
        } 
        // Logic for TAI (Tests Always Included) analyzer.
        elseif ($analyzer === 'tai' && isset($analysisData['tai']) && isset($analysisData['tai']['strengthCode'])) {
            $code = $analysisData['tai']['strengthCode'];
            $taiLevels = ['VERY_WEAK'=>0, 'WEAK'=>1, 'REASONABLE'=>2, 'MEDIUM'=>2, 'STRONG'=>3, 'VERY_STRONG'=>3];
            if (isset($taiLevels[$code])) { $level = $taiLevels[$code]; }
            else { $level = 0; } // Default to weakest if code not recognized.
            $levelText = $analysisData['tai']['strengthMeaning'] ?? $code;
            if (empty($levelText) || $levelText === 'N/A') $levelText = $code ?: "N/A";
            if ($level === 0) $recommendation = "Avoid (TAI: " . $levelText . ")";
            elseif ($level === 1) $recommendation = "Not Recommended (TAI: " . $levelText . ")";
            elseif ($level === 2) $recommendation = "Usable with caution (TAI: " . $levelText . ")";
            elseif ($level === 3) $recommendation = "Good to use (TAI: " . $levelText . ")";
        } 
        // Logic for OWASP NPM (owasp-password-strength-test) analyzer.
        elseif ($analyzer === 'owasp_npm' && isset($analysisData['owasp_npm_results']) && !isset($analysisData['owasp_npm_results']['error'])) {
            $owaspNpm = $analysisData['owasp_npm_results'];
            $isStrong = $owaspNpm['strong'] ?? false;
            $errorsCount = isset($owaspNpm['errors']) && is_array($owaspNpm['errors']) ? count($owaspNpm['errors']) : 0;
            $warningsCount = isset($owaspNpm['warnings']) && is_array($owaspNpm['warnings']) ? count($owaspNpm['warnings']) : 0;

            if ($isStrong && $warningsCount === 0) { $level = 3; $levelText = "Strongly Compliant"; $recommendation = "Excellent (Passes all OWASP npm tests)";}
            elseif ($isStrong) { $level = 2; $levelText = "Compliant (with warnings)"; $recommendation = "Good (Passes OWASP npm tests, but has warnings)";}
            elseif (!$isStrong && $errorsCount > 0 && $errorsCount <= 2) { $level = 1; $levelText = "Partially Compliant"; $recommendation = "Consider improving (Fails few OWASP npm tests)"; }
            else { $level = 0; $levelText = "Non-Compliant"; $recommendation = "Avoid (Fails OWASP npm tests)"; }
        }
    }

    // Construct HTML for the strength bar.
    $barHtml = '<div class="strength-bar-container">';
    for ($i = 0; $i < 4; $i++) {
        $isActive = ($i === $level);
        $segmentColor = $isActive ? $barColors[$i] : 'var(--segment-inactive-bg)';
        $segmentTextColor = $isActive ? $barTextColors[$level] : 'var(--text-muted)';
        $barHtml .= '<div class="strength-bar-segment" style="background-color: ' . $segmentColor . '; color: ' . $segmentTextColor . ';">' . ($isActive ? strtoupper($barSegmentTexts[$level]) : '&nbsp;') . '</div>';
    }
    $barHtml .= '</div>';
    return ['bar' => $barHtml, 'recommendation' => $recommendation, 'level_text' => $levelText, 'level_int' => $level];
}

/**
 * Displays a full array of data in an HTML table, typically for detailed analysis results.
 *
 * @param array $data The array of data to display.
 * @param string $title The title for this data section.
 * @param bool $isTai Special formatting flags for TAI data.
 * @param bool $isZxcvbnOther Special formatting flags for "Other Zxcvbn Data".
 * @return string HTML string representing the table.
 */
function displayFullArray(array $data, string $title, bool $isTai = false, bool $isZxcvbnOther = false): string {
    $html = "<h4 class='data-title'>" . htmlspecialchars($title) . "</h4>";
    if (empty($data)) {
        return $html . "<p>No data available.</p>";
    }
    $html .= "<table class='data-table'>";
    foreach ($data as $key => $value) {
        // Skip internal debug keys or keys not meant for direct display.
        if (strpos($key, 'debug_') === 0 || $key === 'command_executed_for_tai' || $key === 'raw_output_from_node_script_for_parsing_error' || $key === 'last_json_error_message') continue;
        // Skip specific keys for TAI display that are handled elsewhere or are internal.
        if ($isTai && ($key === 'strengthMeaning' || $key === 'debug_stderr_from_node_script')) continue;
        // Skip password field for "Other Zxcvbn Data" as it's redundant.
        if ($isZxcvbnOther && $key === 'password') continue;

        // Prefer 'calc_time_ms' over 'calc_time' if both exist.
        if (!$isTai && ($key === 'calc_time' && isset($data['calc_time_ms']))) {
            $keyToDisplay = 'calc_time_ms';
            $valueToDisplay = $data['calc_time_ms'];
        } elseif (!$isTai && $key === 'calc_time_ms') {
            $keyToDisplay = 'calc_time_ms';
            $valueToDisplay = $value;
        } elseif (!$isTai && $key === 'calc_time') {
            $keyToDisplay = 'calc_time';
            $valueToDisplay = $value;
        } else {
            $keyToDisplay = $key;
            $valueToDisplay = $value;
        }
        // If we've chosen 'calc_time_ms', skip the original 'calc_time' if it comes up later.
        if (!$isTai && $key === 'calc_time' && isset($data['calc_time_ms'])) continue;

        // Format key for display (ucfirst, replace underscores and camelCase with spaces).
        $displayKey = htmlspecialchars(ucfirst(preg_replace(['/(?<!^)[A-Z]/', '/_/'], [' $0', ' '], $keyToDisplay)));
        
        // Format value based on its type or specific key.
        if ($isTai && (strtolower($keyToDisplay) === 'commonpassword')) {
            // Special boolean display for TAI's commonPassword.
            $displayValue = $valueToDisplay ? '<span class="char-type char-absent">YES</span>' : '<span class="char-type char-present">NO</span>';
        } elseif (is_bool($valueToDisplay)) {
            $displayValue = $valueToDisplay ? '<span style="color:var(--color-success); font-weight:bold;">Yes</span>' : '<span style="color:var(--color-danger); font-weight:bold;">No</span>';
        } elseif ($valueToDisplay === null && $isTai && $keyToDisplay === 'trigraphEntropyBits') {
            $displayValue = 'N/A (null value from library)';
        } elseif (is_array($valueToDisplay)) {
            // Handle TAI charsets array specifically for better visual representation.
            if ($isTai && strtolower($keyToDisplay) === 'charsets' && is_array($valueToDisplay)) {
                $displayValue = '<div class="char-type-display">';
                $charsetNameMapping = [
                    'number' => 'Numbers', 'lower' => 'Lowercase', 'upper' => 'Uppercase',
                    'punctuation' => 'Punctuation (TAI)', 'symbol' => 'Symbols (TAI)',
                    'other' => 'Other Unicode'
                ];
                foreach($valueToDisplay as $cKey => $cVal) {
                    $label = $charsetNameMapping[strtolower($cKey)] ?? ucfirst($cKey);
                    $present = false;
                    $displayText = htmlspecialchars($label);
                    if (is_bool($cVal)) { $present = $cVal; }
                    elseif (strtolower($cKey) === 'other') { // 'other' charset in TAI can contain the actual characters.
                        $present = !empty($cVal);
                        if ($present) { $displayText .= ': ' . htmlspecialchars($cVal); }
                    } elseif ($cVal !== null && (string)$cVal !== '') { // Other array values if not boolean or 'other'.
                         $present = true; $displayText .= ': ' . htmlspecialchars((string)$cVal);
                    }
                    $displayValue .= '<span class="char-type ' . ($present ? 'char-present' : 'char-absent') . '">' . $displayText . '</span> ';
                }
                $displayValue .= '</div>';
            } else {
                // Default array display: print_r within <pre> tags.
                $displayValue = '<pre>' . htmlspecialchars(print_r($valueToDisplay, true)) . '</pre>';
            }
        } elseif (is_float($valueToDisplay) && (stripos($keyToDisplay, 'entropy') !== false || stripos($keyToDisplay, 'bits') !== false)) {
            // Format float values for entropy/bits to 2 decimal places.
            $displayValue = htmlspecialchars((string)round($valueToDisplay, 2));
        } elseif (($keyToDisplay === 'calc_time_ms' || $keyToDisplay === 'calc_time') && is_numeric($valueToDisplay)) {
            // Format calculation time.
             $displayValue = htmlspecialchars((string)round($valueToDisplay, 2)) . ' ms';
        } else {
            // Default display for other scalar types.
            $displayValue = htmlspecialchars((string)$valueToDisplay);
        }
        $html .= "<tr><th>" . $displayKey . "</th><td>" . $displayValue . "</td></tr>";
    }
    $html .= "</table>";
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Advanced Password Generator & Analyzer</title>  <link rel="icon" href="favicon.ico">
    <style>
        /* CSS Styles remain unchanged from your provided version. */
        /* For brevity, I'm not repeating the full CSS block here but assume it's the same. */
        :root {
            --bg-color: #1e1e1e; --text-color: #e0e0e0; --container-bg: #2a2a2a; --section-bg: #2c2c2c;
            --border-color: #444; --input-bg: #333; --input-text: #e0e0e0; --input-border: #555;
            --input-focus-border: #007bff; --input-focus-shadow: rgba(0,123,255,.35);
            --button-bg: #007bff; --button-text: #fff; --button-hover-bg: #0056b3;
            --header-color: #e0e0e0; --sub-header-color: #0099ff; --link-color: #61dafb;
            --text-muted: #888; --password-display-bg: #333; --password-display-text: #61dafb;
            --error-bg: #5c2329; --error-text: #f8d7da; --error-border: #8c333a;
            --debug-bg: #303030; --debug-border: #454545; --table-th-bg: #33373a; --table-border: #454545;
            --pre-bg: #272822; --pre-text: #f8f8f2; --segment-inactive-bg: #404040;
            --color-success: #28a745; --color-danger: #dc3545;
        }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin: 0; background-color: var(--bg-color); color: var(--text-color); line-height: 1.6; }
        .container { background-color: var(--container-bg); padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.5); max-width: 1000px; margin: 30px auto; }
        h1 { color: var(--header-color); text-align: center; margin-bottom: 30px; font-weight: 300; letter-spacing: -0.5px; }
        h2.section-title { color: var(--button-bg); border-bottom: 2px solid var(--button-bg); padding-bottom: 10px; margin-top: 40px; margin-bottom:25px; font-weight: 400; font-size: 1.8em; text-align:center; }
        h3.test-section-title { font-weight: 600; font-size: 1.4em; color: var(--header-color); margin-bottom: 20px; padding-bottom:8px; border-bottom: 1px solid var(--border-color); text-align:left; }
        .form-section-wrapper { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .form-section { padding: 20px; background-color:var(--section-bg); border: 1px solid var(--border-color); border-radius: 6px; }
        .form-section h3 { margin-top:0; color: var(--sub-header-color); font-size: 1.2em; margin-bottom:15px;}
        label { font-weight: 500; display: block; margin-bottom: 8px; color: var(--text-color); }
        input[type="number"], input[type="text"], input[type="submit"], select, .button-link {
            padding: 10px 14px; margin-bottom: 15px; border-radius: 5px;
            border: 1px solid var(--input-border); background-color: var(--input-bg); color: var(--input-text);
            box-sizing: border-box; font-size: 1rem; transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out; width: 100%;
        }
        input[type="number"]:focus, input[type="text"]:focus, select:focus { border-color: var(--input-focus-border); outline: 0; box-shadow: 0 0 0 .2rem var(--input-focus-shadow); }
        input[type="number"] { width: 100px;  }
        input[type="text"]#passphrase_separator { width: 80px; display: inline-block; vertical-align: middle; }
        input[type="submit"], .button-link { cursor: pointer; background-color: var(--button-bg); color: var(--button-text); border-color: var(--button-bg); font-weight: 500; margin-top:10px; text-align: center; text-decoration: none; display: inline-block; }
        input[type="submit"]:hover, .button-link:hover { background-color: var(--button-hover-bg); border-color: var(--button-hover-bg);}
        .button-link.secondary { background-color: #6c757d; border-color: #6c757d; margin-top: 20px; margin-bottom: 0px;}
        .button-link.secondary:hover { background-color: #5a6268; border-color: #5a6268;}
        .password-display-wrapper { display: flex; align-items: center; justify-content: center; margin-bottom: 10px; }
        .password-display { font-size: 1.4em; font-weight: 500; color: var(--password-display-text); background-color: var(--password-display-bg); padding: 10px 15px; border-radius: 5px; word-break: break-all; margin-right: 10px; }
        #copyPasswordBtn { background-color: #6c757d; color: white; border: none; padding: 8px 10px; border-radius: 5px; cursor: pointer; display: inline-flex; align-items: center; line-height: 1; }
        #copyPasswordBtn:hover { background-color: #5a6268; }
        #copyPasswordBtn svg { margin-right: 0px; }
        #copyFeedback { font-size: 0.9em; color: var(--color-success); margin-left: 8px; font-style: italic; }
        .error { color: var(--error-text); background-color: var(--error-bg); border: 1px solid var(--error-border); padding: 12px; border-radius: 5px; margin-bottom:20px; }
        .debug-info { background-color: var(--debug-bg); color: var(--text-color); padding: 10px; border: 1px dashed var(--border-color); margin-top:15px; font-size:0.85em; white-space: pre-wrap; word-wrap: break-word; max-height: 250px; overflow-y: auto; border-radius: 4px;}
        .analysis-results-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 30px; align-items: stretch; }
        .test-section { padding:20px; border:1px solid var(--border-color); border-radius:6px; background-color:var(--section-bg); box-shadow: 0 2px 4px rgba(0,0,0,0.2); display: flex; flex-direction: column; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom:15px; }
        .data-table th, .data-table td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--border-color); vertical-align: top; font-size: 0.9rem; }
        .data-table tr:last-child th, .data-table tr:last-child td { border-bottom: none; }
        .data-table th { background-color: var(--table-th-bg); width: 40%; font-weight: 500; color: var(--text-color); }
        .data-table td pre { background-color: var(--pre-bg); color: var(--pre-text); padding: 8px; border-radius: 4px; white-space: pre-wrap; word-wrap: break-word; margin:0; font-size: 0.9em; }
        .data-title { font-size:1.1em; color: var(--sub-header-color); margin-bottom:10px; border-bottom:1px solid var(--border-color); padding-bottom:5px;}
        .strength-bar-container { display: flex; height: 28px; border-radius: 5px; overflow: hidden; margin-bottom: 12px; border: 1px solid var(--border-color);}
        .strength-bar-segment { flex-grow: 1; display:flex; align-items:center; justify-content:center; font-size: 0.8em; font-weight: bold; text-transform: uppercase;}
        .recommendation { padding: 12px; margin-top: 10px; border-radius: 5px; font-weight: 500; text-align: center; font-size:0.95em; }
        .recommendation.level-0 { background-color: var(--color-danger); color: #fff; border: 1px solid #c82333;}
        .recommendation.level-1 { background-color: #fd7e14; color: #fff; border: 1px solid #d3680c;}
        .recommendation.level-2 { background-color: #ffc107; color: #212529; border: 1px solid #e0a800;}
        .recommendation.level-3 { background-color: var(--color-success); color: #fff; border: 1px solid #218838;}
        .char-type-display { margin-bottom:10px;}
        .char-type { padding: 3px 8px; border-radius: 4px; font-size: 0.9em; margin-right: 6px; display:inline-block; margin-bottom:5px; color: #fff; }
        .char-present { background-color: var(--color-success); }
        .char-absent { background-color: var(--color-danger); font-weight: bold; }
        ul.test-failures, ul.test-warnings { list-style-type: disc; padding-left: 20px; margin-top: 5px; margin-bottom: 5px; }
        ul.test-failures li { color: #f8d7da; }
        ul.test-warnings li { color: #ffeeba; }
        .hibp-status { padding: 10px; text-align: center; font-weight: bold; border-radius: 4px; margin-top: 5px; }
        .hibp-pwned { background-color: var(--color-danger); color: white; border: 1px solid #c82333; }
        .hibp-not-pwned { background-color: var(--color-success); color: white; border: 1px solid #218838; }
        .hibp-error { background-color: #ffc107; color: #212529; border: 1px solid #e0a800; }
        .hibp-loading { font-style: italic; color: var(--text-muted); }
        .options-group div { margin-bottom: 8px;}
        .options-group label {display: inline-block; margin-right: 5px; width: auto; font-weight:normal; color: var(--text-color);}
        .options-group input[type="checkbox"] {vertical-align: middle; width: auto; margin-right: 3px;}
        a { color: var(--link-color); }
        a:hover { color: #8adefd; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Advanced Password Generator & Analyzer</h1>
        
        <?php // Display general error messages only when in form view. ?>
        <?php if ($errorMessage && !$show_results_view): ?>
            <p class="error"><?php echo htmlspecialchars($errorMessage); ?></p>
        <?php endif; ?>

        <?php // Display input forms if not in results view. ?>
        <?php if (!$show_results_view): ?>
        <div class="form-section-wrapper">
            <div class="form-section">
                <form method="POST" action="index.php">
                    <h3>Generate Random Password</h3>
                    <div>
                        <label for="length">Length (10-128):</label>
                        <input type="number" id="length" name="length" value="<?php echo htmlspecialchars((string)$length); ?>" min="10" max="128">
                    </div>
                    <div class="options-group">
                        <div>
                            <input type="checkbox" id="random_add_unicode_cb" name="random_add_unicode_cb" value="1" <?php if ($random_add_unicode) echo 'checked'; ?>>
                            <label for="random_add_unicode_cb">Include a Unicode character</label>
                            <small style="display:block; margin-left:22px; color:var(--text-muted); font-size:0.8em;" title="<?php echo htmlspecialchars(implode(' ', array_keys(PasswordHelper::UNICODE_CHARS))); ?>">
                                (<?php echo htmlspecialchars(implode(' ', array_keys(PasswordHelper::UNICODE_CHARS))); ?>)
                            </small>
                        </div>
                    </div>
                    <input type="hidden" name="gen_type" value="random_password">
                    <input type="submit" name="action" value="generate_random" title="Generate and Analyze Random Password">
                </form>
            </div>

            <div class="form-section">
                <form method="POST" action="index.php">
                    <h3>Generate Passphrase</h3>
                    <div>
                        <label for="passphrase_word_count">Number of words (2-10):</label>
                        <input type="number" id="passphrase_word_count" name="passphrase_word_count" value="<?php echo htmlspecialchars((string)$passphrase_word_count); ?>" min="2" max="10">
                    </div>
                    <div>
                        <label for="passphrase_separator">Separator:</label>
                        <input type="text" id="passphrase_separator" name="passphrase_separator" value="<?php echo htmlspecialchars($passphrase_separator); ?>" maxlength="3" title="e.g., '-', ' ', '_' ">
                        <small>(e.g., '-', ' ', '_')</small>
                    </div>
                    <div class="options-group">
                        <div>
                            <input type="checkbox" id="passphrase_capitalize_cb" name="passphrase_capitalize_cb" value="1" <?php if ($passphrase_capitalize) echo 'checked'; ?>>
                            <label for="passphrase_capitalize_cb">Capitalize first letter of each word</label>
                        </div>
                        <div>
                            <input type="checkbox" id="passphrase_add_number_cb" name="passphrase_add_number_cb" value="1" <?php if ($passphrase_add_number) echo 'checked'; ?>>
                            <label for="passphrase_add_number_cb">Add a random digit at the end</label>
                        </div>
                        <div>
                            <input type="checkbox" id="passphrase_add_symbol_cb" name="passphrase_add_symbol_cb" value="1" <?php if ($passphrase_add_symbol) echo 'checked'; ?>>
                            <label for="passphrase_add_symbol_cb">Add a random symbol at the end</label>
                        </div>
                        <div>
                            <input type="checkbox" id="passphrase_add_unicode_cb" name="passphrase_add_unicode_cb" value="1" <?php if ($passphrase_add_unicode) echo 'checked'; ?>>
                            <label for="passphrase_add_unicode_cb">Add a Unicode character at the end</label>
                            <small style="display:block; margin-left:22px; color:var(--text-muted); font-size:0.8em;" title="<?php echo htmlspecialchars(implode(' ', array_keys(PasswordHelper::UNICODE_CHARS))); ?>">
                                (<?php echo htmlspecialchars(implode(' ', array_keys(PasswordHelper::UNICODE_CHARS))); ?>)
                            </small>
                        </div>
                    </div>
                    <div style="margin-top:10px; font-size:0.8em;">
                        <label for="passphrase_dict_display" style="display:block;">Using dictionary:</label>
                        <input type="text" id="passphrase_dict_display_only" value="<?php echo htmlspecialchars($passphrase_dict); ?>" readonly title="Dictionary path (default from PasswordHelper.php)">
                        <input type="hidden" name="passphrase_dict" value="<?php echo htmlspecialchars($passphrase_dict); ?>">
                    </div>
                    <input type="hidden" name="gen_type" value="passphrase">
                    <input type="submit" name="action" value="generate_passphrase" title="Generate and Analyze Passphrase">
                </form>
            </div>

            <div class="form-section">
                <h3>Analyze an Existing Password/Passphrase</h3>
                <form method="POST" action="index.php">
                    <label for="input_password">Password/Passphrase to analyze:</label>
                    <input type="text" id="input_password" name="input_password" value="<?php echo htmlspecialchars($input_password); ?>" placeholder="Enter password or passphrase here...">
                    <input type="submit" name="action" value="analyze_input" title="Analyze Entered Password/Passphrase">
                </form>
            </div>
        </div>
        <?php endif; // End of form view ?>


        <?php // Display analysis results if a password has been analyzed successfully. ?>
        <?php if ($show_results_view && $password_to_analyze && $analysis): ?>
            <?php // Display errors specific to the analysis process, if any. ?>
            <?php if ($errorMessage): ?>
                <p class="error"><?php echo htmlspecialchars($errorMessage); ?></p>
            <?php endif; ?>
            
            <?php // Button to return to the form view. ?>
            <div style="text-align: center; margin-bottom: 20px;">
                 <a href="index.php?view=form" class="button-link secondary">New Analysis / Back to Home</a>
            </div>

            <div class="password-display-container">
                <h2 class="section-title" style="margin-bottom:10px;">Analyzed Result</h2>
                <div class="password-display-wrapper">
                    <div class="password-display" id="analyzedPasswordValue"><?php echo htmlspecialchars($password_to_analyze); ?></div>
                    <button type="button" id="copyPasswordBtn" title="Copy to clipboard">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M4 1.5H3a2 2 0 0 0-2 2V14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V3.5a2 2 0 0 0-2-2h-1v1h1a1 1 0 0 1 1 1V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3.5a1 1 0 0 1 1-1h1v-1z"/>
                            <path d="M9.5 1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-3a.5.5 0 0 1-.5-.5v-1a.5.5 0 0 1 .5-.5h3zm-3-1A1.5 1.5 0 0 0 5 1.5v1A1.5 1.5 0 0 0 6.5 4h3A1.5 1.5 0 0 0 11 2.5v-1A1.5 1.5 0 0 0 9.5 0h-3z"/>
                        </svg>
                        <span id="copyFeedback"></span>
                    </button>
                </div>
                <?php if ($generated_password_type): ?>
                    <p style="text-align:center; font-size:0.9em; margin-top:5px; margin-bottom:20px;"><small>(This <?php echo htmlspecialchars(strtolower($generated_password_type)); ?> was generated.)</small></p>
                <?php endif; ?>
            </div>

            <div class="analysis-results-grid">
                <?php // Section: General Information & Basic Policy (PHP) ?>
                <div class="test-section">
                    <h3 class="test-section-title">General Information & Basic Policy (PHP)</h3>
                    <?php
                        $owaspPhpData = $analysis['owasp'] ?? null;
                        $owaspPhpVisuals = $owaspPhpData ? getStrengthVisuals('owasp_php', $analysis) : ['bar' => '<p class="error">PHP OWASP data not available.</p>', 'recommendation' => 'N/A', 'level_int' => 0, 'level_text' => 'N/A'];
                    ?>
                    <?php echo $owaspPhpVisuals['bar']; ?>
                    <p class="recommendation level-<?php echo $owaspPhpVisuals['level_int']; ?>">
                        Basic Policy Recommendation: <?php echo htmlspecialchars($owaspPhpVisuals['recommendation']); ?>
                    </p>
                    <table class="data-table">
                        <tr><th>Length</th><td><?php echo htmlspecialchars((string)($owaspPhpData['length'] ?? 'N/A')); ?> characters</td></tr>
                        <tr><th>Character Types Present</th>
                            <td class="char-type-display">
                                <?php
                                $charChecks = [
                                    'Lowercase' => $owaspPhpData['has_lowercase'] ?? false,
                                    'Uppercase' => $owaspPhpData['has_uppercase'] ?? false,
                                    'Numbers' => $owaspPhpData['has_number'] ?? false,
                                    'Symbols (our set)' => $owaspPhpData['has_symbol'] ?? false,
                                    'Unicode' => $owaspPhpData['has_unicode'] ?? false,
                                ];
                                foreach ($charChecks as $label => $present) {
                                    echo '<span class="char-type ' . ($present ? 'char-present' : 'char-absent') . '">' . htmlspecialchars($label) . '</span> ';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr><th>Theoretical Entropy</th><td><?php echo htmlspecialchars((string)($analysis['shannon_theoretical_entropy'] ?? 'N/A')); ?> bits (based on alphabet of <?php echo htmlspecialchars((string)($analysis['alphabet_size_for_theoretical_entropy'] ?? 'N/A'));?> characters)</td></tr>
                        <tr><th>Basic Policy Compliance</th><td><?php echo ($owaspPhpData['is_strong'] ?? false) ? '<span style="color:var(--color-success);font-weight:bold;">Compliant</span>' : '<span style="color:var(--color-danger);font-weight:bold;">Non-Compliant</span>'; ?> (<?php echo htmlspecialchars((string)($owaspPhpData['rules_passed'] ?? 'N/A')); ?> / 6 rules)</td></tr>
                        <tr><th>Basic Policy Message</th><td><?php echo htmlspecialchars($owaspPhpData['compliance_message'] ?? 'N/A'); ?></td></tr>
                    </table>
                    <?php if (isset($analysis['warning_strlen_fallback'])): ?>
                        <p class="error"><?php echo htmlspecialchars($analysis['warning_strlen_fallback']); ?></p>
                    <?php endif; ?>
                    
                    <?php // Debug info for Alphabet Calculation - only shown if not in production mode. ?>
                    <?php if (!$isProduction && isset($analysis['debug_alphabet_calculation_details'])): ?>
                    <div class="debug-info">
                        <strong>Debug - Alphabet for Theoretical Entropy:</strong>
                        <pre>
OWASP Detected Char Types (PHP): <?php echo htmlspecialchars(print_r($analysis['owasp_char_types_detected'] ?? [], true)); ?>
1. Concatenated Base String: <?php echo htmlspecialchars($analysis['debug_alphabet_calculation_details']['1_concatenated_base_string'] ?? 'N/A'); ?>
2. Strlen of Concatenated (byte count): <?php echo htmlspecialchars((string)($analysis['debug_alphabet_calculation_details']['2_strlen_of_concatenated_string_(expected_byte_count)'] ?? 'N/A')); ?>
4. Count after str_split: <?php echo htmlspecialchars((string)($analysis['debug_alphabet_calculation_details']['4_count_after_str_split'] ?? 'N/A')); ?>
6. Final Alphabet Size (count after array_unique): <?php echo htmlspecialchars((string)($analysis['debug_alphabet_calculation_details']['6_final_alphabet_size_used'] ?? 'N/A')); ?>
7. Reconstructed Unique Alphabet String: <?php echo htmlspecialchars($analysis['debug_alphabet_calculation_details']['7_reconstructed_unique_alphabet_string'] ?? 'N/A'); ?>
                        </pre>
                    </div>
                    <?php endif; ?>
                </div>

                <?php // Section: Zxcvbn Analysis (Dropbox) ?>
                <div class="test-section">
                    <h3 class="test-section-title">Zxcvbn Analysis (Dropbox)</h3>
                    <?php
                        $zxcvbnFull = $analysis['zxcvbn_full_results'] ?? null;
                        $zxcvbnVisuals = $zxcvbnFull ? getStrengthVisuals('zxcvbn', $analysis) : ['bar' => '<p class="error">Zxcvbn data not available.</p>', 'recommendation' => 'N/A', 'level_text' => 'N/A', 'level_int' => 0];
                    ?>
                    <?php echo $zxcvbnVisuals['bar']; ?>
                    <p class="recommendation level-<?php echo $zxcvbnVisuals['level_int']; ?>">
                        Zxcvbn Recommendation: <?php echo htmlspecialchars($zxcvbnVisuals['recommendation']); ?>
                    </p>
                    <table class="data-table">
                        <tr><th>Overall Score</th><td><?php echo htmlspecialchars((string)($zxcvbnFull['score'] ?? 'N/A')); ?>/4 (<?php echo htmlspecialchars($zxcvbnVisuals['level_text'] ?? 'N/A'); ?>)</td></tr>
                        <?php if (!empty($zxcvbnFull['feedback']['warning'])): ?>
                            <tr><th>Warning</th><td><span style="color:orange;"><?php echo htmlspecialchars($zxcvbnFull['feedback']['warning']); ?></span></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($zxcvbnFull['feedback']['suggestions'])): ?>
                            <tr><th>Suggestions</th><td><pre><?php echo htmlspecialchars(implode("\n", $zxcvbnFull['feedback']['suggestions'])); ?></pre></td></tr>
                        <?php endif; ?>
                    </table>
                    <?php echo displayFullArray($zxcvbnFull['crack_times_display'] ?? [], "Crack Time Estimates (Zxcvbn)"); ?>
                    <?php
                        $otherZxcvbnData = $zxcvbnFull;
                        if ($otherZxcvbnData) {
                            // Unset data already displayed or internal to avoid redundancy in "Other Zxcvbn Data".
                            unset(
                                $otherZxcvbnData['password'], $otherZxcvbnData['score'], $otherZxcvbnData['feedback'],
                                $otherZxcvbnData['crack_times_display'], $otherZxcvbnData['crack_times_seconds'],
                                $otherZxcvbnData['sequence'], $otherZxcvbnData['calc_time'] , $otherZxcvbnData['calc_time_ms']
                            );
                        }
                    ?>
                    <?php echo displayFullArray($otherZxcvbnData ?? [], "Other Zxcvbn Data", false, true); ?>
                    
                    <?php // Debug info for Zxcvbn sequence - only shown if not in production mode. ?>
                    <?php if (!$isProduction && isset($analysis['zxcvbn_full_results']['sequence'])): ?>
                     <div class="debug-info"><strong>Zxcvbn Debug - `sequence` (match analysis):</strong><pre><?php echo htmlspecialchars(print_r($analysis['zxcvbn_full_results']['sequence'], true)); ?></pre></div>
                    <?php endif; ?>
                </div>

                <?php // Section: Detailed OWASP Test (npm: owasp-password-strength-test) ?>
                <div class="test-section">
                    <h3 class="test-section-title">Detailed OWASP Test (npm: `owasp-password-strength-test`)</h3>
                    <?php
                        $owaspNpmData = $analysis['owasp_npm_results'] ?? null;
                        $owaspNpmVisuals = ($owaspNpmData && !isset($owaspNpmData['error'])) ? getStrengthVisuals('owasp_npm', $analysis) : ['bar' => '<p class="error">Detailed OWASP (npm) data not available or error in Node script.</p>', 'recommendation' => 'Check Node.js script output below if available.', 'level_int' => 0, 'level_text' => 'Error'];
                    ?>
                    <?php echo $owaspNpmVisuals['bar']; ?>
                    <p class="recommendation level-<?php echo $owaspNpmVisuals['level_int']; ?>">
                        Detailed OWASP (npm) Recommendation: <?php echo htmlspecialchars($owaspNpmVisuals['recommendation']); ?>
                    </p>
                    <?php if ($owaspNpmData && !isset($owaspNpmData['error'])): ?>
                        <table class="data-table">
                            <tr><th>Overall Result (Strong by library)</th><td><?php echo ($owaspNpmData['strong'] ?? false) ? '<span style="color:var(--color-success);font-weight:bold;">Yes</span>' : '<span style="color:var(--color-danger);font-weight:bold;">No</span>'; ?></td></tr>
                            <?php if (isset($owaspNpmData['passedTests']) && is_array($owaspNpmData['passedTests'])): ?>
                                <tr><th>Number of Passed Test Conditions</th><td><?php echo htmlspecialchars(count($owaspNpmData['passedTests'])); ?></td></tr>
                            <?php endif; ?>
                            <?php if (!empty($owaspNpmData['errors'])): ?>
                                <tr><th>Failed Required Rules (Errors)</th><td><ul class="test-failures"><?php foreach($owaspNpmData['errors'] as $err) { echo '<li>' . htmlspecialchars($err) . '</li>'; } ?></ul></td></tr>
                            <?php endif; ?>
                            <?php if (!empty($owaspNpmData['warnings'])): ?>
                                <tr><th>Failed Optional Rules (Warnings)</th><td><ul class="test-warnings"><?php foreach($owaspNpmData['warnings'] as $warn) { echo '<li>' . htmlspecialchars($warn) . '</li>'; } ?></ul></td></tr>
                            <?php endif; ?>
                            <?php if (isset($owaspNpmData['isPassphrase'])): ?>
                                <tr><th>Considered as Passphrase</th><td><?php echo ($owaspNpmData['isPassphrase']) ? 'Yes' : 'No'; ?></td></tr>
                            <?php endif; ?>
                        </table>
                    <?php elseif (isset($owaspNpmData['error'])): ?>
                        <p class="error">Error processing Detailed OWASP (npm) data: <?php echo htmlspecialchars($owaspNpmData['error']);?></p>
                         <?php if (isset($owaspNpmData['details'])): ?> <p class="error">Details: <?php echo htmlspecialchars($owaspNpmData['details']); ?></p> <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php // Section: TAI Analysis (Tests Always Included) ?>
                <div class="test-section">
                    <h3 class="test-section-title">TAI Analysis (Tests Always Included)</h3>
                    <?php
                        $taiData = $analysis['tai'] ?? null;
                        $taiVisuals = ($taiData && (!isset($taiData['error']) || (isset($taiData['error']) && isset($taiData['strengthCode'])) ) ) ? getStrengthVisuals('tai', $analysis) : ['bar' => '<p class="error">TAI analysis not available or encountered an error.</p>', 'recommendation' => 'Check Node.js script output below.', 'level_int' => 0, 'level_text' => 'Error'];
                    ?>
                    <?php echo $taiVisuals['bar']; ?>
                    <p class="recommendation level-<?php echo $taiVisuals['level_int']; ?>">
                        TAI Recommendation: <?php echo htmlspecialchars($taiVisuals['recommendation']); ?>
                    </p>
                    <?php
                        // Display TAI results or error messages.
                        if (isset($taiData['error']) && !isset($taiData['strengthCode'])){
                            // Error is critical and no strength code available (handled by visuals already).
                        } elseif (!empty($taiData) && !isset($taiData['error'])) {
                            echo displayFullArray($taiData, "Detailed TAI Results", true);
                        } elseif (isset($taiData['error'])) { // Error occurred, but some data (like strengthCode) might still be present.
                             echo '<p class="error">TAI Error Detail: '.htmlspecialchars($taiData['error']).'</p>';
                             if(isset($taiData['details'])) echo '<p class="error">Details: '.htmlspecialchars($taiData['details']).'</p>';
                             if (isset($taiData['strengthCode'])) { // If partial data available, display it.
                                 echo displayFullArray($taiData, "Detailed TAI Results (possibly partial)", true);
                             }
                        }
                    ?>
                </div>

                <?php // Section: Additional Entropy Calculations (Node.js) ?>
                <div class="test-section">
                    <h3 class="test-section-title">Additional Entropy Calculations (Node.js)</h3>
                    <?php
                        $fastEntropyData = $analysis['fast_entropy_results'] ?? null;
                        $stringEntropyData = $analysis['string_entropy_results'] ?? null;
                    ?>
                    <table class="data-table">
                        <?php if ($fastEntropyData && !isset($fastEntropyData['error'])): ?>
                            <tr><th>Shannon Entropy (fast-password-entropy)</th><td><?php echo htmlspecialchars(round($fastEntropyData['shannonEntropyBits'], 2)); ?> bits</td></tr>
                        <?php elseif ($fastEntropyData && isset($fastEntropyData['error'])): ?>
                             <tr><th>Shannon Entropy (fast-password-entropy)</th><td><span style="color:var(--color-danger);">Error: <?php echo htmlspecialchars($fastEntropyData['error']); ?> (Details: <?php echo htmlspecialchars($fastEntropyData['details'] ?? 'N/A'); ?>)</span></td></tr>
                        <?php else: ?>
                            <tr><th>Shannon Entropy (fast-password-entropy)</th><td>N/A - Data not processed</td></tr>
                        <?php endif; ?>

                        <?php if ($stringEntropyData && !isset($stringEntropyData['error'])): ?>
                            <tr><th>Shannon Entropy (string-entropy)</th><td><?php echo htmlspecialchars(round($stringEntropyData['shannonEntropyBits'], 2)); ?> bits</td></tr>
                        <?php elseif ($stringEntropyData && isset($stringEntropyData['error'])): ?>
                             <tr><th>Shannon Entropy (string-entropy)</th><td><span style="color:var(--color-danger);">Error: <?php echo htmlspecialchars($stringEntropyData['error']); ?> (Details: <?php echo htmlspecialchars($stringEntropyData['details'] ?? 'N/A'); ?>)</span></td></tr>
                        <?php else: ?>
                             <tr><th>Shannon Entropy (string-entropy)</th><td>N/A - Data not processed</td></tr>
                        <?php endif; ?>
                    </table>
                </div>

                <?php // Section: Have I Been Pwned (HIBP) Check ?>
                <div class="test-section">
                    <h3 class="test-section-title">Have I Been Pwned (HIBP) Check</h3>
                    <div id="hibpResult" class="hibp-loading">Checking password against HIBP database... (Requires secure context: HTTPS or localhost)</div>
                    <p id="hibpRecommendation" class="recommendation" style="display:none;"></p>
                    <p style="font-size:0.8em; text-align:center; margin-top:10px;">
                        <a href="https://haveibeenpwned.com/Passwords" target="_blank" rel="noopener noreferrer">Learn more about Pwned Passwords</a>
                        (This check is performed client-side for privacy using k-Anonymity).
                    </p>
                </div>

                <?php // Section: Node.js Script Execution Debug ?>
                <?php
                 // Determine if the Node.js debug block should be shown.
                 // Show if:
                 // 1. There's a script execution error OR a JSON parsing error from the script.
                 // OR
                 // 2. It's NOT production AND raw output from the script exists (even if it's valid JSON, for dev inspection).
                 $showNodeDebugBlock = (isset($analysis['node_script_execution_error']) || isset($analysis['node_script_parsing_error'])) ||
                                     (!$isProduction && isset($analysis['node_script_raw_output']) && !empty(trim($analysis['node_script_raw_output'])));
                 if ($showNodeDebugBlock):
                ?>
                 <div class="test-section">
                    <h3 class="test-section-title">Node.js Script Execution Debug</h3>
                    <?php if(isset($analysis['node_script_execution_error'])): ?>
                        <p class="error">Execution Error: <?php echo htmlspecialchars($analysis['node_script_execution_error']); ?></p>
                    <?php endif; ?>
                    <?php if(isset($analysis['node_script_parsing_error'])): ?>
                        <p class="error">JSON Parsing Error from Node script: <?php echo htmlspecialchars($analysis['node_script_parsing_error']); ?></p>
                        <?php if(isset($analysis['node_script_json_error_message'])): ?>
                            <p class="error">JSON Error Detail: <?php echo htmlspecialchars($analysis['node_script_json_error_message']); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php // Display raw Node.js script output only if NOT in production and output exists. ?>
                    <?php if (!$isProduction && isset($analysis['node_script_raw_output']) && !empty(trim($analysis['node_script_raw_output']))): ?>
                        <div class="debug-info"><strong>Raw `shell_exec` output (includes stdout & stderr from Node script):</strong><pre><?php echo htmlspecialchars($analysis['node_script_raw_output']); ?></pre></div>
                    <?php endif; ?>
                 </div>
                 <?php endif; // End of Node.js Debug Block ?>

            </div> <?php // End of .analysis-results-grid ?>
        <?php endif; // End of results view ?>
    </div> <?php // End of .container ?>

    <?php // JavaScript for HIBP check and Copy to Clipboard functionality. ?>
    <script>
        /**
         * Calculates the SHA-1 hash of a string.
         * Requires a secure context (HTTPS or localhost) for crypto.subtle API.
         * @param {string} str The string to hash.
         * @returns {Promise<string>} The uppercase hexadecimal SHA-1 hash.
         * @throws {Error} If crypto features are unavailable.
         */
        async function sha1(str) {
            if (!window.crypto || !window.crypto.subtle || typeof window.crypto.subtle.digest !== 'function') {
                console.error("SHA-1: crypto.subtle is not available.");
                throw new Error("Browser crypto features not available (requires secure context: HTTPS or localhost).");
            }
            const buffer = new TextEncoder().encode(str);
            const hashBuffer = await crypto.subtle.digest('SHA-1', buffer);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
            return hashHex.toUpperCase();
        }

        /**
         * Checks a password against the Have I Been Pwned (HIBP) database using k-Anonymity.
         * Updates the DOM with the result.
         * @param {string} password The password to check.
         */
        async function checkHIBP(password) {
            const hibpResultEl = document.getElementById('hibpResult');
            const hibpRecommendationEl = document.getElementById('hibpRecommendation');

            if (!hibpResultEl || !hibpRecommendationEl) {
                console.error("HIBP DOM elements not found for displaying results.");
                return;
            }

            // Check for crypto.subtle availability (secure context).
            if (!window.crypto || !window.crypto.subtle || typeof window.crypto.subtle.digest !== 'function') {
                hibpResultEl.textContent = "Error: HIBP check requires a secure context (HTTPS or localhost) for SHA-1 hashing in the browser.";
                hibpResultEl.className = 'hibp-status hibp-error';
                hibpRecommendationEl.textContent = "The browser's secure crypto features are not available. Please access this page over HTTPS or from localhost to enable this check.";
                hibpRecommendationEl.className = 'recommendation level-1'; // Orange for warning/error.
                hibpRecommendationEl.style.display = 'block';
                console.warn("crypto.subtle is not available. HIBP check cannot be performed.");
                return;
            }

            if (!password) {
                hibpResultEl.textContent = "No password provided for HIBP check.";
                hibpResultEl.className = 'hibp-status hibp-error';
                hibpRecommendationEl.style.display = 'none';
                return;
            }

            hibpResultEl.textContent = "Checking HIBP database...";
            hibpResultEl.className = 'hibp-status hibp-loading';
            hibpRecommendationEl.style.display = 'none'; // Hide recommendation while loading.

            try {
                const hash = await sha1(password);
                const prefix = hash.substring(0, 5); // First 5 chars of the hash for k-Anonymity.
                const suffix = hash.substring(5);     // The rest of the hash to check locally.
                
                const apiUrl = `https://api.pwnedpasswords.com/range/${prefix}`;
                const response = await fetch(apiUrl, { method: 'GET', headers: { 'Add-Padding': 'true' } }); // Add-Padding for better cache behavior.

                if (!response.ok) {
                    if (response.status === 404) { // Prefix not found, means password (suffix) is not pwned.
                        hibpResultEl.textContent = "Good: Not found in any known data breaches (prefix not found in HIBP).";
                        hibpResultEl.className = 'hibp-status hibp-not-pwned';
                        hibpRecommendationEl.textContent = "This password was not found in publicly available data breaches checked by HIBP. This is positive, but continue to ensure overall password strength.";
                        hibpRecommendationEl.className = 'recommendation level-3'; // Green for good.
                        hibpRecommendationEl.style.display = 'block';
                        return;
                    }
                    // Handle other API errors.
                    let errorText = `HIBP API error: ${response.status} ${response.statusText}. `;
                    if (response.status === 403) { errorText += "Access to HIBP API was forbidden (check User-Agent or API key if applicable in other contexts, though not for this public API)."; }
                    else if (response.status === 429) { errorText += "You might be rate-limited by HIBP API. Try again later."; }
                    throw new Error(errorText);
                }

                const text = await response.text(); // Get the list of suffixes.
                const lines = text.split(/\r\n|\n|\r/); // Split by newline characters.
                let pwnedCount = 0;

                // Check if our suffix is in the list.
                for (let line of lines) {
                    const parts = line.split(':'); // Format is SUFFIX:COUNT
                    if (parts.length === 2 && parts[0] === suffix) {
                        pwnedCount = parseInt(parts[1], 10);
                        break;
                    }
                }

                if (pwnedCount > 0) {
                    hibpResultEl.textContent = `Warning: Found in ${pwnedCount.toLocaleString()} known data breach${pwnedCount > 1 ? 'es' : ''}!`;
                    hibpResultEl.className = 'hibp-status hibp-pwned';
                    hibpRecommendationEl.textContent = "AVOID THIS PASSWORD! It has appeared in data breaches and is compromised.";
                    hibpRecommendationEl.className = 'recommendation level-0'; // Red for pwned.
                } else {
                    hibpResultEl.textContent = "Good: Not found in any known data breaches (checked against HIBP).";
                    hibpResultEl.className = 'hibp-status hibp-not-pwned';
                    hibpRecommendationEl.textContent = "This password was not found in publicly available data breaches checked by HIBP. This is positive, but continue to ensure overall password strength.";
                    hibpRecommendationEl.className = 'recommendation level-3'; // Green for good.
                }
                hibpRecommendationEl.style.display = 'block';

            } catch (err) {
                console.error("Error checking HIBP:", err);
                hibpResultEl.textContent = "Error: Could not check HIBP status. " + (err.message || "Please try again later or check browser console.");
                hibpResultEl.className = 'hibp-status hibp-error';
                hibpRecommendationEl.textContent = "Unable to determine if this password has been pwned due to a technical issue.";
                hibpRecommendationEl.className = 'recommendation level-1'; // Orange for warning/error.
                hibpRecommendationEl.style.display = 'block';
            }
        }

        // Event listener for DOMContentLoaded to set up interactions.
        document.addEventListener('DOMContentLoaded', function() {
            // --- Copy to Clipboard Functionality ---
            const copyBtn = document.getElementById('copyPasswordBtn');
            const passwordEl = document.getElementById('analyzedPasswordValue');
            const copyFeedbackEl = document.getElementById('copyFeedback');

            if (copyBtn && passwordEl) {
                copyBtn.addEventListener('click', function() {
                    const passwordText = passwordEl.innerText || passwordEl.textContent;
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        // Modern async clipboard API.
                        navigator.clipboard.writeText(passwordText).then(function() {
                            if(copyFeedbackEl) { 
                                copyFeedbackEl.textContent = 'Copied!'; 
                                copyBtn.style.backgroundColor = 'var(--color-success)'; // Green feedback.
                                setTimeout(function() { 
                                    copyFeedbackEl.textContent = ''; 
                                    copyBtn.style.backgroundColor = '#6c757d'; // Revert to original color.
                                }, 2000); 
                            }
                        }).catch(function(err) { 
                            if(copyFeedbackEl) { copyFeedbackEl.textContent = 'Failed!'; } 
                            console.error('Failed to copy (navigator.clipboard): ', err); 
                        });
                    } else {
                        // Fallback for older browsers (deprecated but widely supported).
                        const textArea = document.createElement('textarea');
                        textArea.value = passwordText;
                        textArea.style.position = 'fixed'; // Prevent screen scroll.
                        textArea.style.left = '-9999px'; // Move out of view.
                        document.body.appendChild(textArea);
                        textArea.focus();
                        textArea.select();
                        try {
                            document.execCommand('copy');
                            if(copyFeedbackEl) { 
                                copyFeedbackEl.textContent = 'Copied (fallback)!'; 
                                copyBtn.style.backgroundColor = 'var(--color-success)';
                                setTimeout(function() { 
                                    copyFeedbackEl.textContent = ''; 
                                    copyBtn.style.backgroundColor = '#6c757d'; 
                                }, 2000); 
                            }
                        } catch (err) {
                            if(copyFeedbackEl) { copyFeedbackEl.textContent = 'Failed!';}
                            console.error('Fallback: Unable to copy to clipboard', err);
                        }
                        document.body.removeChild(textArea);
                    }
                });
            }

            // --- Conditional HIBP Check ---
            // This PHP block injects the password to check if we are in the results view.
            <?php if ($show_results_view && $password_to_analyze): ?>
            const passwordToCheck = <?php echo json_encode($password_to_analyze); ?>;
            if (passwordToCheck) {
                checkHIBP(passwordToCheck);
            }
            <?php endif; ?>
            
            // The JavaScript for equalizing card heights has been removed as per your request.
        });
    </script>
</body>
</html>
