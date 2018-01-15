<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Fixer\PhpUnit;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Indicator\PhpUnitTestCaseIndicator;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;

/**
 * @author Gert de Pagter
 */
final class PhpUnitSetUpTearDownVisibilityFixer extends AbstractFixer
{
    /**
     * {@inheritdoc}
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Changes the visibility of the setUp and tearDown functions of phpunit to protected, to match the PHPUnit TestCase.',
            [
                new CodeSample(
                    '<?php
final class MyTest extends \PHPUnit_Framework_TestCase
{
    private $hello;
    public function setUp()
    {
        $this->hello = "hello";
    }

    public function tearDown()
    {
        $this->hello = null;
    }
}
'
                ),
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function isCandidate(Tokens $tokens)
    {
        return $tokens->isAllTokenKindsFound([T_CLASS, T_FUNCTION]);
    }

    /**
     * {@inheritdoc}
     */
    protected function applyFix(\SplFileInfo $file, Tokens $tokens)
    {
        foreach (array_reverse($this->findPhpUnitClasses($tokens)) as $indexes) {
            $this->fixSetUpAndTearDown($tokens, $indexes[0], $indexes[1]);
        }
    }

    /**
     * @param Tokens $tokens
     *
     * @return int[][] array of [start, end] indexes from sooner to later classes
     */
    private function findPhpUnitClasses(Tokens $tokens)
    {
        $phpUnitTestCaseIndicator = new PhpUnitTestCaseIndicator();
        $phpunitClasses = [];

        for ($index = 0, $limit = $tokens->count() - 1; $index < $limit; ++$index) {
            if ($tokens[$index]->isGivenKind(T_CLASS) && $phpUnitTestCaseIndicator->isPhpUnitClass($tokens, $index)) {
                $index = $tokens->getNextTokenOfKind($index, ['{']);
                $endIndex = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $index);
                $phpunitClasses[] = [$index, $endIndex];
                $index = $endIndex;
            }
        }

        return $phpunitClasses;
    }

    /**
     * @param Tokens $tokens
     * @param int    $startIndex
     * @param int    $endIndex
     */
    private function fixSetUpAndTearDown(Tokens $tokens, $startIndex, $endIndex)
    {
        $counter = 0;
        $tokensAnalyzer = new TokensAnalyzer($tokens);

        for ($i = $endIndex - 1; $i > $startIndex; --$i) {
            if ($counter >= 2) {
                break;
            }

            if (!$this->isMethodSetUpOrTearDown($tokens, $i)) {
                continue;
            }

            ++$counter;

            $visibility = $tokensAnalyzer->getMethodAttributes($i)['visibility'];

            if (T_PROTECTED === $visibility || T_PRIVATE === $visibility) {
                continue;
            }

            if (T_PUBLIC === $visibility) {
                $index = $tokens->getPrevTokenOfKind($i, [[T_PUBLIC]]);
                $tokens[$index] = new Token([T_PROTECTED, 'protected']);

                continue;
            }

            if (null === $visibility) {
                $tokens->insertAt($i, [new Token([T_PROTECTED, 'protected']), new Token([T_WHITESPACE, ' '])]);
            }
        }
    }

    /**
     * @param Tokens $tokens
     * @param int    $index
     *
     * @return bool
     */
    private function isMethodSetUpOrTearDown(Tokens $tokens, $index)
    {
        $tokensAnalyzer = new TokensAnalyzer($tokens);

        $isMethod = $tokens[$index]->isGivenKind(T_FUNCTION) && !$tokensAnalyzer->isLambda($index);
        if (!$isMethod) {
            return false;
        }

        $functionNameIndex = $tokens->getNextMeaningfulToken($index);
        $functionName = \strtolower($tokens[$functionNameIndex]->getContent());

        return 'setup' === $functionName || 'teardown' === $functionName;
    }
}