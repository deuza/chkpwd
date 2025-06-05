// analyze_tai.js
const taiPasswordStrength = require('tai-password-strength');
const owaspPasswordStrengthTest = require('owasp-password-strength-test');
const fastPasswordEntropyFunction = require('fast-password-entropy'); 
const stringEntropyModule = require('string-entropy'); // Import the module object

const password = process.argv[2];

if (!password) {
    console.error(JSON.stringify({ error: "No password provided to TAI script" }));
    process.exit(1);
}

const results = {};

// --- TAI Analysis ---
try {
    const PasswordStrengthClass = taiPasswordStrength.PasswordStrength;
    if (!PasswordStrengthClass) {
        results.tai = { error: "TAI Internal Error: PasswordStrength class could not be loaded." };
    } else {
        const strength = new PasswordStrengthClass();
        strength.addCommonPasswords(taiPasswordStrength.commonPasswords);
        const taiResults = strength.check(password);

        const TAI_STRENGTH_MEANINGS = {
          VERY_WEAK: 'Very Weak', WEAK: 'Weak', REASONABLE: 'Reasonable',
          MEDIUM: 'Medium', STRONG: 'Strong', VERY_STRONG: 'Very Strong'
        };
        taiResults.strengthMeaning = TAI_STRENGTH_MEANINGS[taiResults.strengthCode] || taiResults.strengthCode || 'N/A';
        results.tai = taiResults;
    }
} catch (e) {
    results.tai = { 
        error: "Error during TAI password analysis", 
        details: e.message,
        stack: e.stack 
    };
}

// --- OWASP Password Strength Test (npm) Analysis ---
try {
    const owaspResults = owaspPasswordStrengthTest.test(password);
    results.owasp_npm = owaspResults;
} catch (e) {
    results.owasp_npm = { 
        error: "Error during OWASP (npm) analysis", 
        details: e.message,
        stack: e.stack
    };
}

// --- Fast Password Entropy Analysis ---
try {
    results.fast_entropy = {
        shannonEntropyBits: fastPasswordEntropyFunction(password) 
    };
} catch (e) {
    results.fast_entropy = { 
        error: "Error during Fast Password Entropy analysis",
        details: e.message,
        stack: e.stack
    };
}

// --- String Entropy Analysis ---
try {
    // Corrected call: using the 'entropy' method from the imported module object
    if (stringEntropyModule && typeof stringEntropyModule.entropy === 'function') {
        results.string_entropy = {
            shannonEntropyBits: stringEntropyModule.entropy(password) 
        };
    } else {
        results.string_entropy = { 
            error: "String Entropy module structure unexpected.",
            details: "Expected an object with an 'entropy' function based on previous tests."
        };
        // console.error("Debug (analyze_tai.js): stringEntropyModule content was: " + JSON.stringify(stringEntropyModule));
    }
} catch (e) {
    results.string_entropy = { 
        error: "Error during String Entropy analysis",
        details: e.message,
        stack: e.stack
    };
}

console.log(JSON.stringify(results));
