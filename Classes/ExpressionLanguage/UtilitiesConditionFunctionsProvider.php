<?php

namespace JambageCom\Patch10011\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use TYPO3\CMS\Core\ExpressionLanguage\ProviderInterface;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use JambageCom\Patch10011\Utility\ExtensionManagementUtility as PatchExtensionManagementUtility;

class UtilitiesConditionFunctionsProvider implements ExpressionFunctionProviderInterface, ProviderInterface
{
    /**
     * @var array
     */
    protected $existingVariables;

    /**
     * Sets the existing variables context.
     *
     * @param array $existingVariables
     * @return void
     */
    public function setExistingVariables($existingVariables)
    {
        $this->existingVariables = $existingVariables;
    }

    /**
     * Gets the existing variables context.
     *
     * @return array
     */
    public function getExistingVariables()
    {
        return $this->existingVariables;
    }

    /**
     * TYPO3 Core interface requirement: Returns the function providers as class-strings.
     *
     * @return array<int, class-string<ExpressionFunctionProviderInterface>>
     */
    public function getExpressionLanguageProviders(): array
    {
        return [
            self::class
        ];
    }

    /**
     * TYPO3 Core interface requirement: Returns the custom variables.
     *
     * @return array
     */
    public function getExpressionLanguageVariables(): array
    {
        return [];
    }

    /**
     * Symfony interface requirement: Returns an array of Function instances
     *
     * @return ExpressionFunction[]
     */
    public function getFunctions(): array
    {
        return [
            $this->getUserFunction(),
            $this->getExtensionVersionFunction(),
        ];
    }

    /**
     * @return ExpressionFunction
     */
    protected function getUserFunction(): ExpressionFunction
    {
        return new ExpressionFunction('userFunc', function () {
            return '';
        }, function ($variables, ...$arguments) {
            $funcName = '';
            $result = false;

            if (isset($arguments[0])) {
                $funcName = $arguments[0];
            }

            if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['patch10011']['userFunc'][$funcName])) {
                $className = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['patch10011']['userFunc'][$funcName];

                if (class_exists($className)) {
                    $hookObj = new $className();
                    $value = [];

                    if (method_exists($hookObj, 'init')) {
                        $hookObj->init($variables, $value);
                    }

                    $funcValue = array_slice($arguments, 1);

                    if (method_exists($hookObj, $funcName)) {
                        $this->setExistingVariables($variables);
                        $result = call_user_func_array([$hookObj, $funcName], [&$funcValue, $this]);
                    }
                }
            }

            return $result;
        });
    }

    /**
     * Registers the 'ext' function inside TypoScript conditions.
     * High-performance string-padding implementation utilizing a lean Mini-Compiler for Symfony 7.
     * Requires the TypoScript condition to use 2-digit padding (e.g., "03.05.99").
     *
     * @return ExpressionFunction
     */
    protected function getExtensionVersionFunction(): ExpressionFunction
    {
        return new ExpressionFunction(
            'ext',
            // 1. MINI-COMPILER CALLBACK: Mandatory requirement for a callable in Symfony 7.
            // Dynamically forwards the compiled template evaluation directly to the live evaluator below.
            function (...$arguments) {
                return sprintf('$functions[\'ext\'][\'evaluator\']($variables, %s)', implode(', ', $arguments));
            },
            // 2. LIVE EVALUATOR CALLBACK: Processes active runtime execution.
            function ($variables, ...$arguments) {
                $result = false;

                if (!isset($arguments[0])) {
                    return false;
                }

                $extensionKey = (string)$arguments[0];
                $type = isset($arguments[1]) ? (string)$arguments[1] : null;

                $isLoaded = ExtensionManagementUtility::isLoaded($extensionKey);
                if ($isLoaded) {
                    if (null === $type) {
                        $result = true;
                    } else {
                        $extInfoArray = PatchExtensionManagementUtility::getExtensionInfo($extensionKey);

                        if (is_array($extInfoArray) && isset($extInfoArray[$type])) {
                            if ($type === 'version') {
                                $result = PatchExtensionManagementUtility::makeVersion($extInfoArray[$type], '');
                            } else {
                                $result = $extInfoArray[$type];
                            }
                        }
                    }
                }

                return $result;
            }
        );
    }
}
