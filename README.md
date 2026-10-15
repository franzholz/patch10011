# TYPO3 extension patch10011

## What it does

This extension provides methods to enhance the TypoScript with a userFunc and a check for installed extensions and their version numbers. TYPO3 from version 13.4 to 14.3 are supported.

Use the forum at https://jambage.com to ask questions and find answers.
The documentation file `manual.odt` is available in the doc folder.

---

## Mandatory TypoScript Syntax (Version Constraints)

Because the Symfony Expression Language evaluates conditional operators (`>`, `<`, `==`, `<=`, `>=`) as strict character-by-character alphabetic string comparisons, **both sides of the comparator must maintain an identical number of digits between the dots.**

To enforce 100% mathematical precision and lightning-fast frontend caching, the internal engine strictly formats extracted version strings using a **two-digit padding format** (e.g., `03.06.00`).

### Mandatory Leading Zeros!
You **must** always format your target version strings inside TypoScript conditions using leading zeros to achieve full string symmetry.

### Examples
```typoscript
[ext("news")]
  # TypoScript lines which will be active only if news has been installed and loaded.
[end]
```

```typoscript
# Correct Usage: Matching lengths against padded versions (3.6.0 becomes "03.06.00")
[ext("tt_products", "version") > "03.05.99"]
  # TypoScript lines active only if tt_products is greater than 3.5.99
[end]
```

```typoscript
# Correct Usage: Checking for a major release constraint
[ext("news", "version") > "14.00.00"]
  # TypoScript lines active only if news version is greater than 14.0.0
[end]
```

```typoscript
# INCORRECT USAGE: Do not use unpadded strings!
# This will fail because ASCII '0' from "03.06.00" evaluates as smaller than '3'
[ext("tt_products", "version") > "3.5.99"]
```

---

## Internal Formatting Matrix

The underlying `ExtensionManagementUtility::makeVersion()` engine guarantees structural padding consistency across every lifecycle check. Regardless of how values are specified inside the extension's `composer.json`, the output aligns perfectly:

| Raw Configuration Field | Standardized Output String |
| :---------------------- | :------------------------- |
| `3.6.0`                 | `03.06.00`                 |
| `14.3.7`                | `14.03.07`                 |
| `1.0.2`                 | `01.00.02`                 |
| `10.0.0`                | `10.00.00`                 |

---

## Enhancement

Use the hook `$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['patch10011']['userFunc']` in order to enhance this extension by any other TYPO3 extension which wants to provide its special userFunc to admins.

---

## Technical Architecture & Caching

The function leverages a minimal, highly optimized native compiler block combined with runtime evaluation:
- **Compiler Stage**: Generates a lean flat-string routine during template compilation to satisfy Symfony 7 constraints.
- **Evaluator Stage**: Fetches local metadata structures instantly out of the extension's `composer.json` without extra database overhead.

Whenever you alter conditions or metadata constraints inside your setup files, make sure to completely purge the compiled template caches using the official TYPO3 Command Line Interface:

```bash
php typo3/sysext/core/bin/typo3 cache:flush --group system
```
