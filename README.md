# chkpwd

## 1. Project Purpose

This project is a PHP-based web tool designed for administrators and security-conscious users to:

* Generate strong random passwords of a user-specified length.
* Generate passphrases from dictionary words, with options for capitalization, and appending numbers or symbols.
* Analyze the strength of generated or user-inputted passwords/passphrases using a variety of metrics and analysis engines.
* Provide detailed feedback, including strength scores, estimated crack times (from Zxcvbn), character set composition, compliance with basic policies, and checks against known data breaches (Have I Been Pwned).
* Enhance password security awareness and promote better password practices by offering multiple perspectives on password strength.

The tool aims to be a comprehensive resource by combining different analysis techniques.

## 2. Installation

Follow these steps to set up the Advanced Password Generator & Analyzer on your web server.

### System-Level Dependencies

Ensure your server (e.g., Debian/Ubuntu based) has the following installed:

* **Web Server:** Apache2 (or Nginx, Caddy, etc., with appropriate configuration).
* **PHP:** Version 7.4 or higher is recommended.
    * Required PHP extensions:
        * `mbstring` (for multi-byte string functions like `mb_strlen` used for correct character counting in UTF-8).
        * `json` (for communication with the Node.js script).
        * `iconv` (used for passphrase generation to transliterate words, ensure `//TRANSLIT` is supported).
        * `phar` (often enabled by default, needed by Composer).
    * Example installation: `sudo apt update && sudo apt install php php-mbstring php-json php-iconv php-phar apache2 libapache2-mod-php`
* **SSL for Apache2 (Recommended for HIBP):**
    * To enable the client-side Have I Been Pwned check (which uses `crypto.subtle` API), the page should be served over HTTPS or accessed via `localhost`.
    * Enable `mod_ssl`: `sudo a2enmod ssl`
    * Configure an SSL-enabled virtual host (e.g., by enabling `default-ssl.conf` with `sudo a2ensite default-ssl.conf` and ensuring valid certificates are configured, even self-signed for local use).
    * Restart Apache: `sudo systemctl restart apache2`
* **Dictionary File (for Passphrase Generation):**
    * The tool defaults to `/usr/share/dict/words`. This is often a link to an English dictionary.
    * Install a dictionary if missing: `sudo apt install wamerican` (for American English) or `wfrench` etc. The `dictionaries-common` package often manages this.
* **Node.js and npm:**
    * Node.js (LTS version recommended, e.g., 18.x or later).
    * npm (usually comes with Node.js).
    * Example installation: Follow official Node.js installation guides at [nodejs.org](https://nodejs.org/).

### Project Setup & Dependencies

1.  **Clone or Download Project Files:**
    Clone this repository to your web server directory (e.g., `/var/www/html/`):
    ```bash
    git clone https://github.com/deuza/chkpwd.git
    cd chkpwd
    ```
    
    Alternatively, download the files (`index.php`, `PasswordHelper.php`, `analyze_tai.js`, `composer.json`) and place them in the directory.

2.  **Install PHP Dependencies (Composer):**
    The project uses `bjeavons/zxcvbn-php`. Ensure you have a `composer.json` file at the root of the project with the following content:
    ```json
    {
        "require": {
            "bjeavons/zxcvbn-php": "^1.3"
        },
        "config": {
            "optimize-autoloader": true,
            "preferred-install": "dist"
        },
        "minimum-stability": "stable",
        "prefer-stable": true
    }
    ```
    Then, run Composer to install the PHP dependency:
    ```bash
    composer install
    ```
    This will create a `vendor/` directory.
    Alternatively, download the files (index.php, PasswordHelper.php, analyze_tai.js, composer.json, package.json) and place them in the directory.

3.  **Install Node.js Dependencies (npm):**
    The project uses several Node.js packages for analysis. Install them by running:
    ```bash
    npm install
    ```

    This will create a `node_modules/` directory and a `package-lock.json` file.

3.  **Install Node.js Dependencies (npm):**
    The project uses several Node.js packages for analysis, listed in `package.json`. Install them by running:
    ```bash
    npm install
    ```
    This will create a `node_modules/` directory and a `package-lock.json` file (if not already present and up-to-date). If you encounter issues, ensure your Node.js and npm versions are up-to-date and that you have the necessary permissions. (npm install tai-password-strength owasp-password-strength-test fast-password-entropy string-entropy)

### Configuration Notes

* **PHP `shell_exec`:** The `shell_exec` function must be enabled in your PHP configuration (`php.ini`) as it's used to call the `analyze_tai.js` script. Check that it's not listed in the `disable_functions` directive.
* **Node.js Path:** The `PasswordHelper.php` script currently uses an absolute path `/usr/bin/node` to execute the Node.js script. If your `node` executable is located elsewhere, you'll need to update this path in `PasswordHelper.php`.
* **Web Server Configuration:** Configure your web server (e.g., Apache VirtualHost) to point to the project directory and allow access to `index.php`. Ensure `AllowOverride All` is set for the directory if you plan to use `.htaccess` (though not strictly needed for this project as is).
* **File Permissions:** Ensure the web server user (e.g., `www-data`) has read access to all project files and the `vendor/` and `node_modules/` directories.

## 3. Function of Each File

* **`index.php`:**
    * The main user interface (frontend). Handles user input for password/passphrase generation or direct analysis. Displays the generated credential and all detailed analysis results, including strength bars and recommendations. Contains HTML, CSS, and PHP presentation logic, plus client-side JavaScript for HIBP check and copy-to-clipboard functionality.
* **`PasswordHelper.php`:**
    * A PHP class containing the core backend logic.
        * `generatePassword()`: Securely generates random passwords based on length.
        * `generatePassphrase()`: Generates passphrases from a dictionary file with options for word count, separator, capitalization, and appending a number or ASCII symbol.
        * `analyzePassword()`: Orchestrates the password/passphrase analysis by:
            * Calling `zxcvbn-php`.
            * Performing internal "Basic Policy" checks (length, character type diversity based on ASCII sets).
            * Calculating "Theoretical Shannon Entropy" based on detected ASCII character types and character length (using `mb_strlen`).
            * Executing `analyze_tai.js` via `shell_exec` to get results from TAI, OWASP-npm, fast-entropy, and string-entropy.
            * Aggregating all results.
* **`analyze_tai.js`:**
    * A Node.js script that receives a password as a command-line argument.
    * It utilizes the following npm packages for analysis:
        * `tai-password-strength`: For TAI metrics.
        * `owasp-password-strength-test`: For detailed OWASP compliance checks.
        * `fast-password-entropy`: For a raw Shannon entropy calculation.
        * `string-entropy`: For another Shannon entropy calculation (uses `.entropy()` method).
    * It aggregates the results from these libraries and outputs them as a single JSON string.
* **`package.json`:**
    * Defines the Node.js dependencies for Npm.

* **`composer.json`:**
    * Defines the PHP project dependency for Composer.

## 4. Password Strength Tests Explained

This tool uses multiple analyzers to provide a holistic view of password/passphrase strength, as different tools focus on different aspects.

### a. General Information & Basic Policy (PHP)

* **Interest/Purpose:** This section provides fundamental details about the password and checks it against a basic, customizable policy inspired by common security recommendations (e.g., from OWASP, NIST). It gives a quick first assessment of hygiene.
* **How it Works:**
    * **Length:** Calculated using `mb_strlen` for correct UTF-8 character counting.
    * **Character Types Present:** Detects lowercase, uppercase, numbers, and symbols (from a predefined 28-symbol ASCII set: `!@#$%^&*()_+-=[]{}|;:,.<>/?~`).
    * **Theoretical Shannon Entropy:** Calculated as $L \times \log_2(N)$.
        * $L$ is the character length of the password.
        * $N$ is the size of the effective alphabet based *only* on the detected presence of the four ASCII character types mentioned above (e.g., if all four are present, $N = 26+26+10+28 = 90$). This entropy measures the password's strength *if it were randomly generated solely from these combined ASCII sets*. It does not dynamically include other Unicode characters from the password in *this specific* calculation.
    * **Basic Policy Compliance:** Checks if length >= 10 AND at least 3 of the 4 ASCII character types (lowercase, uppercase, numbers, symbols) are present.
* **Recommendation:** Based on whether the basic policy is met.
* **Source Links:**
    * OWASP Password Guidelines: [Password Storage Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html), [Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
    * NIST SP 800-63B (Digital Identity): [Section 5.1.1 Memorized Secrets](https://pages.nist.gov/800-63-3/sp800-63b.html)

### b. Zxcvbn Analysis (Dropbox)

* **Interest/Purpose:** Zxcvbn is a powerful strength estimator that goes beyond simple character counting. It attempts to mimic common attack vectors by checking against extensive dictionaries (common passwords, names, English words, movie/TV titles) and recognizing patterns (sequences, keyboard layouts, L33t speak, repeats). Its goal is to estimate how many "guesses" an attacker would need.
* **How it Works:** It breaks the password into a sequence of matched patterns and calculates the entropy (and thus guess count) for each pattern, summing them up. It penalizes easily guessable patterns.
* **Output:** A score (0-4), detailed guess estimations, human-readable crack time estimations for various attack scenarios (e.g., offline fast hashing, online throttling), and often direct warnings or suggestions for improvement.
* **Recommendation:** Based on the 0-4 score.
* **Source Links:**
    * Zxcvbn Project (Original): [https://github.com/dropbox/zxcvbn](https://github.com/dropbox/zxcvbn)
    * PHP Port Used: (`bjeavons/zxcvbn-php`) [https://github.com/bjeavons/zxcvbn-php](https://github.com/bjeavons/zxcvbn-php)

### c. Detailed OWASP Test (npm: `owasp-password-strength-test`)

* **Interest/Purpose:** This Node.js library provides a test suite based on specific OWASP recommendations for password strength. It offers more granular feedback on which rules are passed or failed.
* **How it Works:** It checks the password against a configurable set of rules (defaulting to OWASP recommendations like minimum length, character classes, no repeating characters, etc.).
* **Output:** An overall `strong` (boolean) status, and arrays of `errors` (for failed required tests) and `warnings` (for failed optional tests) containing human-readable messages. It also provides `passedTests` and `failedTests` arrays (containing internal test IDs).
* **Recommendation:** Based on the `strong` status and the presence/number of `errors` and `warnings`.
* **Source Links:**
    * `owasp-password-strength-test` on npm: [https://www.npmjs.com/package/owasp-password-strength-test](https://www.npmjs.com/package/owasp-password-strength-test)

### d. TAI Analysis (Tests Always Included)

* **Interest/Purpose:** `tai-password-strength` offers another perspective on password strength, using different statistical models and checks. It's particularly interesting for its dynamic charset analysis.
* **How it Works:** It performs several checks including its own Shannon entropy calculation, NIST-based entropy, trigraph analysis (though this often returns null), common password list lookup, and analysis of the character sets present in the password (including "other" Unicode characters).
* **Output:** A `strengthCode` (e.g., "VERY_WEAK", "STRONG") with a corresponding `strengthMeaning`, various entropy figures, a `commonPassword` flag, and a detailed `charsets` object showing which types of characters (including non-ASCII as "other") it detected and used for its `charsetSize`.
* **Note on TAI's Password Processing:** TAI normalizes passwords (e.g., by removing all whitespace characters). For very long passwords or those with many non-ASCII characters, it might analyze a version that is effectively shorter or different from the original input (e.g., it was observed to process a 160-char mixed input as 155 chars, but a 128-char ASCII-generated password at full length). This can influence its results.
* **Recommendation:** Based on its `strengthCode`.
* **Source Links:**
    * `tai-password-strength` on npm: [https://www.npmjs.com/package/tai-password-strength](https://www.npmjs.com/package/tai-password-strength)

### e. Additional Entropy Calculations (Node.js)

* **Interest/Purpose:** To provide purely mathematical Shannon entropy calculations based on the character frequency within the given password string itself, from two different lightweight libraries. This can offer a contrast to model-based entropies (like TAI's or Zxcvbn's implicit entropy) or our theoretical entropy (which assumes a known generation alphabet).
* **How it Works:**
    * `fast-password-entropy`: Calculates Shannon entropy based on character distribution in the input string.
    * `string-entropy`: Also calculates Shannon entropy, potentially with a slightly different approach or precision.
* **Output:** A raw Shannon entropy value in bits from each library.
* **Recommendation:** These are presented as data points and do not have a separate strength bar or recommendation; they inform the overall picture.
* **Source Links:**
    * `fast-password-entropy` on npm: [https://www.npmjs.com/package/fast-password-entropy](https://www.npmjs.com/package/fast-password-entropy)
    * `string-entropy` on npm: [https://www.npmjs.com/package/string-entropy](https://www.npmjs.com/package/string-entropy)

### f. Have I Been Pwned (HIBP) Check

* **Interest/Purpose:** This checks if the exact password has appeared in known public data breaches aggregated by HIBP. A password can be theoretically strong (high entropy, complex) but still be compromised if it's been leaked.
* **How it Works:** Uses the k-Anonymity model. The password is SHA-1 hashed client-side (in the user's browser). Only the first 5 characters of the hash are sent to the HIBP API. HIBP returns a list of hash suffixes matching that prefix. The browser then locally checks if the password's full hash suffix is in the returned list. This preserves privacy as the full password or its full hash is never sent.
* **Output:** "Not Pwned" or "Pwned X times!"
* **Recommendation:** If pwned, the password should be AVOIDED, regardless of other strength metrics.
* **Source Links:**
    * Have I Been Pwned - Pwned Passwords: [https://haveibeenpwned.com/Passwords](https://haveibeenpwned.com/Passwords)

### g. Others interesting sources

* xkcd (https://www.xkcd.com/936/)
* xkcd analysis #1 (https://www.reddit.com/r/xkcd/comments/8vb9x3/is_password_strength_still_legit/)
* xkcd analysis #2 (https://www.reddit.com/r/technology/comments/2j7jvr/password_security_why_xkcds_horse_battery_staple/)
* Analysis password tools (https://rumkin.com/tools/password/)
* EFF dices (https://www.eff.org/dices)
* Guide auto-défense numérique (https://guide.boum.org/)


## 5. Screenshots


| Main interface                                      | test 128 chars                                      | 128 Test1                                      |
| :-------------------------------------------------: | :-------------------------------------------------: | :--------------------------------------------: |
| ![Main interface](https://imgur.com/ndQxTwY)        | ![test 128 chars](https://imgur.com/HMFKYSx)        | ![Test1](https://imgur.com/jUhOanF)            |
| 128 Zxcvbn                                          | 128 Zxcvbn                                          | 128 OWASP                                      |
| ![zxcvbn](https://imgur.com/d8OQYrZ)                | ![zxcvbn](https://imgur.com/Bf7gR1r)                | ![OWASP](https://imgur.com/CDEY8n2)            |
| 128 TAI                                             | 128 Entropy                                         | 128 HIBP                                       |
| ![TAI](https://imgur.com/OtuZnIE)                   | ![Entropy](https://imgur.com/LjLkPFi)               | ![HIBP](https://github.com/deuza/a03.png)      |
| 128 HIBP                                            | test admin                                          | admin Test1                                    |
| ![HIBP](https://imgur.com/F3BJq9X)                  | ![test admin](https://imgur.com/WH4FB1G)            | ![Test1](https://imgur.com/nVyS7KV)            |
| admin Zxcvbn                                        | admin Zxcvbn                                        | admin OWASP                                    |
| ![zxcvbn](https://imgur.com/wdCBmEc)                | ![zxcvbn](https://imgur.com/4IjeKc7)                | ![OWASP](https://imgur.com/fYp7Wsy)            |
| admin TAI                                           | admin Entropy                                       | admin HIBP                                     |
| ![TAI](https://imgur.com/0ipIseX)                   | ![Entropy](https://imgur.com/7eCNylB)               | ![HIBP](https://imgur.com/JEgirN0)             |
| admin Execution debug                               | 128 Execution debug                                 | HIB Error HTTP                                 |
| ![Debug](https://imgur.com/vTw5mJE)                 | ![Debug](https://imgur.com/O5UMxiU)                 | ![HIBP HTTP ERROR](https://imgur.com/Bdl3h33)  |


## 6. Limitations and Known Behaviors

* **TAI `trigraphEntropyBits: null`:** The TAI library consistently returns `null` for this metric in our tests.
* **TAI Password Normalization:** TAI preprocesses passwords (e.g., removes spaces, may truncate very long or complex strings), which can affect its analysis length and results compared to the raw input.
* **Theoretical Entropy (PHP):** This calculation is based on the password's character length and an alphabet size derived *only* from detected characters within predefined ASCII sets (lowercase, uppercase, numbers, and the 28 `DEFAULT_SYMBOLS`). It does *not* dynamically expand its reference alphabet for other Unicode characters present in the input password for *this specific "Theoretical Entropy"* value (though TAI and other Node.js entropy tools will consider them). When all four basic ASCII types are present, the alphabet size used is 90.
* **Node.js Dependency:** Several key analyses (`TAI`, `OWASP npm`, additional entropies) rely on Node.js and `shell_exec`.
* **Dictionary Path for Passphrases:** Passphrase generation defaults to `/usr/share/dict/words`. Its availability and content can vary between systems. The UI currently shows this path but does not allow user modification for security reasons (to prevent path traversal attacks without robust server-side validation).
* **Internet Connection for HIBP:** The HIBP check is performed client-side and requires an internet connection to reach the HIBP API. It also requires a secure context (HTTPS or localhost) for browser crypto features.

---
*This tool is provided for educational and informational purposes. Always follow comprehensive security best practices.*
