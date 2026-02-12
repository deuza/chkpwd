
<?php
// PasswordHelper.php
require 'vendor/autoload.php';

use ZxcvbnPhp\Zxcvbn;

class PasswordHelper {

    private const DEFAULT_SYMBOLS = '!@#$%^&*()_+-=[]{}|;:,.<>/?~';
    public const DEFAULT_DICTIONARY = '/usr/share/dict/words';

    /** Common Unicode characters for password hardening. */
    public const UNICODE_CHARS = [
        'é' => 'é (e acute)',
        'è' => 'è (e grave)',
        'à' => 'à (a grave)',
        'ù' => 'ù (u grave)',
        'ç' => 'ç (c cedilla)',
        'ñ' => 'ñ (n tilde)',
        'ö' => 'ö (o umlaut)',
        'ü' => 'ü (u umlaut)',
        '€' => '€ (euro sign)',
        '£' => '£ (pound sign)',
        '¥' => '¥ (yen sign)',
        'µ' => 'µ (micro sign)',
        'ø' => 'ø (o stroke)',
        'æ' => 'æ (ae ligature)',
    ];

    public static function generatePassword(int $length, bool $useLowercase = true, bool $useUppercase = true, bool $useNumbers = true, bool $useSymbols = true, bool $addUnicode = true): string {
        if ($length < 1) {
            throw new InvalidArgumentException("Password length must be at least 1.");
        }
        $charset = '';
        $passwordParts = [];
        $charTypesSelected = 0;
        if ($useLowercase) { $charset .= 'abcdefghijklmnopqrstuvwxyz'; $passwordParts[] = 'abcdefghijklmnopqrstuvwxyz'[random_int(0, 25)]; $charTypesSelected++; }
        if ($useUppercase) { $charset .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'; $passwordParts[] = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[random_int(0, 25)]; $charTypesSelected++; }
        if ($useNumbers) { $charset .= '0123456789'; $passwordParts[] = '0123456789'[random_int(0, 9)]; $charTypesSelected++; }
        if ($useSymbols) { $charset .= self::DEFAULT_SYMBOLS; $passwordParts[] = self::DEFAULT_SYMBOLS[random_int(0, strlen(self::DEFAULT_SYMBOLS) - 1)]; $charTypesSelected++; }

        // Guarantee one random Unicode character if requested.
        if ($addUnicode) {
            $unicodeKeys = array_keys(self::UNICODE_CHARS);
            $passwordParts[] = $unicodeKeys[random_int(0, count($unicodeKeys) - 1)];
            $charTypesSelected++;
        }

        if (empty($charset) && !$addUnicode) {
            throw new InvalidArgumentException("At least one character type must be selected for password generation.");
        }
        // Ensure we have a charset for fill characters even if only unicode was selected.
        if (empty($charset)) {
            $charset = 'abcdefghijklmnopqrstuvwxyz';
        }

        if ($length < $charTypesSelected) {
            $passwordParts = array_slice($passwordParts, 0, $length);
            $remainingLength = 0;
        } else {
            $remainingLength = $length - count($passwordParts);
        }
        for ($i = 0; $i < $remainingLength; $i++) {
            $passwordParts[] = $charset[random_int(0, strlen($charset) - 1)];
        }
        shuffle($passwordParts);
        return implode('', $passwordParts);
    }

    public static function generatePassphrase(
        int $wordCount = 4,
        string $separator = '-',
        string $dictionaryFile = self::DEFAULT_DICTIONARY,
        int $minWordLength = 4,
        int $maxWordLength = 8,
        bool $capitalizeWords = true,
        bool $addNumber = true,
        bool $addSymbol = true,
        bool $addUnicode = true
    ): string {
        if ($wordCount < 1) {
            throw new InvalidArgumentException("Word count must be at least 1.");
        }
        if (!file_exists($dictionaryFile) || !is_readable($dictionaryFile)) {
            $altDictionaryFile = '/usr/dict/words';
            if (file_exists($altDictionaryFile) && is_readable($altDictionaryFile)) {
                $dictionaryFile = $altDictionaryFile;
            } else {
                throw new RuntimeException("Dictionary file not found or not readable: " . htmlspecialchars($dictionaryFile) . " (and alternative not found). Please ensure a dictionary is available.");
            }
        }

        $words = file($dictionaryFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($words === false || empty($words)) {
            throw new RuntimeException("Dictionary is empty or could not be read: " . htmlspecialchars($dictionaryFile));
        }

        $filteredWords = array_filter($words, function($word) use ($minWordLength, $maxWordLength) {
            $asciiWord = '';
            if (function_exists('iconv')) {
                $asciiWord = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $word);
                if ($asciiWord === false) $asciiWord = $word;
                $asciiWord = strtolower($asciiWord);
                $asciiWord = preg_replace('/[^a-z]/', '', $asciiWord);
            } else {
                if (preg_match('/^[a-zA-Z]+$/', $word)) {
                    $asciiWord = strtolower($word);
                } else {
                    return false;
                }
            }
            if (empty($asciiWord)) return false;
            $len = strlen($asciiWord);
            return ($len >= $minWordLength && $len <= $maxWordLength);
        });

        if (count($filteredWords) < $wordCount && count($filteredWords) > 0) {
             throw new RuntimeException("Not enough suitable words found in dictionary (need $wordCount, found " . count($filteredWords) . " matching criteria " . $minWordLength . "-" . $maxWordLength . " chars, a-z only). Try adjusting word length constraints or using a larger/different dictionary.");
        } elseif (empty($filteredWords)) {
            throw new RuntimeException("No suitable words found in the dictionary matching criteria (length " . $minWordLength . "-" . $maxWordLength . ", a-z only).");
        }

        $filteredWords = array_values($filteredWords);
        $passphraseParts = [];
        $keys = [];

        if (count($filteredWords) > 0) {
            $numWordsToSelect = min($wordCount, count($filteredWords));
            if ($numWordsToSelect < 1) throw new RuntimeException("Cannot select 0 words.");
            $keys = (array) array_rand($filteredWords, $numWordsToSelect);
        }

        if (empty($keys)) {
             throw new RuntimeException("Could not select random words (keys array is empty).");
        }

        foreach ($keys as $key) {
            $word = $filteredWords[$key];
            if ($capitalizeWords) {
                $word = ucfirst($word);
            }
            $passphraseParts[] = $word;
        }

        $finalPassphrase = implode($separator, $passphraseParts);

        if ($addNumber) {
            $finalPassphrase .= random_int(0, 9);
        }

        if ($addSymbol && !empty(self::DEFAULT_SYMBOLS)) {
            $symbolIndex = random_int(0, strlen(self::DEFAULT_SYMBOLS) - 1);
            $finalPassphrase .= self::DEFAULT_SYMBOLS[$symbolIndex];
        }

        if ($addUnicode) {
            $unicodeKeys = array_keys(self::UNICODE_CHARS);
            $finalPassphrase .= $unicodeKeys[random_int(0, count($unicodeKeys) - 1)];
        }

        return $finalPassphrase;
    }

    public static function analyzePassword(string $password): array {
        $analysis = [];
        $zxcvbn = new Zxcvbn();
        $analysis['zxcvbn_full_results'] = $zxcvbn->passwordStrength($password);
        $analysis['zxcvbn'] = $analysis['zxcvbn_full_results'];
        $charLength = function_exists('mb_strlen') ? mb_strlen($password, 'UTF-8') : strlen($password);
        if (!function_exists('mb_strlen')) {
            $analysis['warning_strlen_fallback'] = "PHP mbstring extension not available; password length might be byte count for UTF-8 strings.";
        }
        $owasp = [
            'length' => $charLength,
            'has_lowercase' => (bool) preg_match('/[a-z]/u', $password),
            'has_uppercase' => (bool) preg_match('/[A-Z]/u', $password),
            'has_number' => (bool) preg_match('/[0-9]/', $password),
            'has_symbol' => (bool) preg_match('/[' . preg_quote(self::DEFAULT_SYMBOLS, '/') . ']/', $password),
            'has_unicode' => (bool) preg_match('/[^\x00-\x7F]/u', $password),
            'is_strong' => false
        ];
        $analysis['owasp_char_types_detected'] = [
            'lowercase_detected' => $owasp['has_lowercase'], 'uppercase_detected' => $owasp['has_uppercase'],
            'number_detected' => $owasp['has_number'], 'symbol_from_set_detected' => $owasp['has_symbol'],
            'unicode_detected' => $owasp['has_unicode']
        ];
        $owasp['rules_passed'] = 0;
        if ($owasp['length'] >= 10) $owasp['rules_passed']++;
        if ($owasp['has_lowercase']) $owasp['rules_passed']++;
        if ($owasp['has_uppercase']) $owasp['rules_passed']++;
        if ($owasp['has_number']) $owasp['rules_passed']++;
        if ($owasp['has_symbol']) $owasp['rules_passed']++;
        if ($owasp['has_unicode']) $owasp['rules_passed']++;
        $diversityScore = ($owasp['has_lowercase'] ? 1:0) + ($owasp['has_uppercase'] ? 1:0) + ($owasp['has_number'] ? 1:0) + ($owasp['has_symbol'] ? 1:0) + ($owasp['has_unicode'] ? 1:0);
        if ($owasp['length'] >= 10 && $diversityScore >= 3) {
            $owasp['is_strong'] = true;
            $owasp['compliance_message'] = "Passes basic OWASP-like policy.";
        } else {
            $owasp['is_strong'] = false;
            $owasp['compliance_message'] = "Fails basic OWASP-like policy (min length 10 and at least 3 character types).";
        }
        $analysis['owasp'] = $owasp;
        $baseAlphabetForEntropyString = '';
        if ($owasp['has_lowercase']) $baseAlphabetForEntropyString .= 'abcdefghijklmnopqrstuvwxyz';
        if ($owasp['has_uppercase']) $baseAlphabetForEntropyString .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if ($owasp['has_number']) $baseAlphabetForEntropyString .= '0123456789';
        if ($owasp['has_symbol']) $baseAlphabetForEntropyString .= self::DEFAULT_SYMBOLS;
        // Add Unicode chars to the theoretical alphabet (conservative estimate: our known set).
        if ($owasp['has_unicode']) $baseAlphabetForEntropyString .= implode('', array_keys(self::UNICODE_CHARS));
        $strlenOfBaseAlphabet = function_exists('mb_strlen') ? mb_strlen($baseAlphabetForEntropyString, 'UTF-8') : strlen($baseAlphabetForEntropyString);
        // Use mb_str_split or preg_split for proper UTF-8 character splitting.
        if (function_exists('mb_str_split')) {
            $baseAlphabetArray = mb_str_split($baseAlphabetForEntropyString, 1, 'UTF-8');
        } else {
            $baseAlphabetArray = preg_split('//u', $baseAlphabetForEntropyString, -1, PREG_SPLIT_NO_EMPTY);
        }
        $uniqueAlphabetChars = array_unique($baseAlphabetArray);
        $alphabetSize = count($uniqueAlphabetChars);
        $analysis['debug_alphabet_calculation_details'] = [
            '1_concatenated_base_string' => $baseAlphabetForEntropyString,
            '2_strlen_of_concatenated_string_(expected_byte_count)' => $strlenOfBaseAlphabet,
            '4_count_after_str_split' => count($baseAlphabetArray),
            '5_array_after_array_unique' => array_values($uniqueAlphabetChars),
            '6_final_alphabet_size_used' => $alphabetSize,
            '7_reconstructed_unique_alphabet_string' => implode('', $uniqueAlphabetChars)
        ];
        if ($alphabetSize > 1 && $owasp['length'] > 0) {
            $analysis['shannon_theoretical_entropy'] = round($owasp['length'] * log($alphabetSize, 2), 2);
            $analysis['alphabet_size_for_theoretical_entropy'] = $alphabetSize;
        } else {
            $analysis['shannon_theoretical_entropy'] = 0;
            $analysis['alphabet_size_for_theoretical_entropy'] = $alphabetSize;
        }
        $nodeScriptPath = __DIR__ . '/analyze_tai.js';
        $command = '/usr/bin/node ' . escapeshellarg($nodeScriptPath) . ' ' . escapeshellarg($password) . ' 2>&1';
        $nodeOutputJson = shell_exec($command);
        $analysis['node_script_raw_output'] = $nodeOutputJson;
        if ($nodeOutputJson === null || $nodeOutputJson === '') {
            $analysis['node_script_execution_error'] = 'Failed to execute Node.js script or script returned empty output.';
            $analysis['tai'] = ['error' => 'Node script execution error'];
            $analysis['owasp_npm_results'] = ['error' => 'Node script execution error'];
            $analysis['fast_entropy_results'] = ['error' => 'Node script execution error'];
            $analysis['string_entropy_results'] = ['error' => 'Node script execution error'];
        } else {
            $allNodeResults = json_decode($nodeOutputJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($allNodeResults)) {
                $analysis['tai'] = $allNodeResults['tai'] ?? ['error' => 'TAI data missing from Node script output'];
                $analysis['owasp_npm_results'] = $allNodeResults['owasp_npm'] ?? ['error' => 'OWASP NPM data missing from Node script output'];
                $analysis['fast_entropy_results'] = $allNodeResults['fast_entropy'] ?? ['error' => 'Fast Entropy data missing from Node script output'];
                $analysis['string_entropy_results'] = $allNodeResults['string_entropy'] ?? ['error' => 'String Entropy data missing from Node script output'];
            } else {
                $analysis['node_script_parsing_error'] = 'Failed to parse JSON from Node.js script output. The output might contain raw error messages.';
                $analysis['node_script_json_error_message'] = json_last_error_msg();
                $analysis['tai'] = ['error' => 'Node script JSON parsing error, or script failed. Check raw output.'];
                $analysis['owasp_npm_results'] = ['error' => 'Node script JSON parsing error, or script failed.'];
                $analysis['fast_entropy_results'] = ['error' => 'Node script JSON parsing error, or script failed.'];
                $analysis['string_entropy_results'] = ['error' => 'Node script JSON parsing error, or script failed.'];
            }
        }
        return $analysis;
    }
}
?>
