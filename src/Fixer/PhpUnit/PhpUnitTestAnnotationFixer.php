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
use PhpCsFixer\DocBlock\DocBlock;
use PhpCsFixer\Fixer\ConfigurationDefinitionFixerInterface;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerConfiguration\FixerOptionBuilder;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\Indicator\PhpUnitTestCaseIndicator;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\Tokenizer\TokensAnalyzer;

/**
 * @author Gert de Pagter
 */
final class PhpUnitTestAnnotationFixer extends AbstractFixer implements ConfigurationDefinitionFixerInterface
{
    private $annotated;

    /**
     * {@inheritdoc}
     */
    public function configure(array $configuration = null)
    {
        parent::configure($configuration);

        $this->annotated = $this->configuration['style'] !== 'prefix';
    }

    /**
     * {@inheritdoc}
     */
    public function getDefinition()
    {
        return new FixerDefinition(
            'Adds or removes @test annotations from tests, following configuration.',
            [
                new CodeSample("<?php
class Test extends \\PhpUnit\\FrameWork\\TestCase
{
    /**
     * @test
     */
    public function itDoesSomething() {} }\n"),
                new CodeSample("<?php
class Test extends \\PhpUnit\\FrameWork\\TestCase
{
public function testItDoesSomething() {}}\n",['style' => 'annotation'])
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
            if ($this->annotated) {
                $this->applyTestAnnotation($tokens, $indexes[0], $indexes[1]);
            } else {
                $this->removeTestAnnotation($tokens, $indexes[0], $indexes[1]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function createConfigurationDefinition()
    {
        return new FixerConfigurationResolver([
            (new FixerOptionBuilder('style', 'Whether to use the @test annotation or not.'))
                ->setAllowedValues(['prefix', 'annotation'])
                ->setDefault('prefix')
                ->setAllowedTypes(['string'])
                ->getOption(),
        ]);
    }

    private function applyTestAnnotation(Tokens $tokens, $startIndex, $endIndex)
    {
        $tokensAnalyzer = new TokensAnalyzer($tokens);

        for ($i = $endIndex - 1; $i > $startIndex; --$i) {
            //ignore non functions
            if (!$tokens[$i]->isGivenKind(T_FUNCTION) || $tokensAnalyzer->isLambda($i)) {
                continue;
            }

            $functionIndex = $i;
            $docBlockIndex = $i;

            // ignore abstract functions
            $braceIndex = $tokens->getNextTokenOfKind($functionIndex, [';', '{']);
            if (!$tokens[$braceIndex]->equals('{')) {
                continue;
            }

            $functionNameIndex = $tokens->getNextMeaningfulToken($functionIndex);
            $functionName = $tokens[$functionNameIndex]->getContent();

            //ignore functions that don't start with test
            if (!$this->startsWith('test', $functionName)) {
                continue;
            }

            $functionName = preg_replace('{test}', '', $functionName, 1);
            $newFunctionName = lcfirst($functionName);
            $newFunctionNameToken = new Token([T_STRING, $newFunctionName]);
            $tokens->offsetSet($functionNameIndex, $newFunctionNameToken);

            //We removed test from the function name, now we have to update the doc blocks
            do {
                $docBlockIndex = $tokens->getPrevNonWhitespace($docBlockIndex);
            } while ($tokens[$docBlockIndex]->isGivenKind([T_PUBLIC, T_PROTECTED, T_PRIVATE, T_FINAL, T_ABSTRACT, T_COMMENT]));

            $originalIndent = $this->detectIndent($tokens, $tokens->getNextNonWhitespace($docBlockIndex));
            //If the function didn't have a DocBlock Before
            if (!$tokens[$docBlockIndex]->isGivenKind(T_DOC_COMMENT)) {
                $toInsert = [
                    new Token([T_WHITESPACE, "\n".$originalIndent]),
                    new Token([T_DOC_COMMENT, "/**\n$originalIndent * @test\n$originalIndent */"]),
                ];
                $tokens->insertAt($docBlockIndex + 1, $toInsert);

                continue;
            }
            $doc = new DocBlock($tokens[$docBlockIndex]->getContent());
            $lines = $doc->getLines();
            array_splice($lines, 1, 0, $originalIndent." * @test\n");
            $lines = implode('', $lines);
            $tokens->clearAt($docBlockIndex);
            $tokens->insertAt($docBlockIndex, new Token([T_DOC_COMMENT, $lines]));
        }
    }

    private function removeTestAnnotation(Tokens $tokens, $startIndex, $endIndex)
    {
        $tokensAnalyzer = new TokensAnalyzer($tokens);

        for ($i = $endIndex - 1; $i > $startIndex; --$i) {
            //ignore non functions
            if (!$tokens[$i]->isGivenKind(T_FUNCTION) || $tokensAnalyzer->isLambda($i)) {
                continue;
            }

            $functionIndex = $i;
            $docBlockIndex = $i;

            // ignore abstract functions
            $braceIndex = $tokens->getNextTokenOfKind($functionIndex, [';', '{']);
            if (!$tokens[$braceIndex]->equals('{')) {
                continue;
            }

            do {
                $docBlockIndex = $tokens->getPrevNonWhitespace($docBlockIndex);
            } while ($tokens[$docBlockIndex]->isGivenKind([T_PUBLIC, T_PROTECTED, T_PRIVATE, T_FINAL, T_ABSTRACT, T_COMMENT]));

            //ignore functions that don't have a dockBlock
            if (!$tokens[$docBlockIndex]->isGivenKind(T_DOC_COMMENT)) {
                continue;
            }
            $docBlock = new DocBlock($tokens[$docBlockIndex]->getContent());
            $lines = $docBlock->getLines();
            for ($j = 0; $j < count($lines); ++$j) {
                if (false !== strpos($lines[$j], '@test')) {
                    $lines[$j]->remove();
                }
            }
            $lines = implode($lines);
            $tokens->offsetSet($docBlockIndex, new Token([T_DOC_COMMENT, $lines]));

            $functionNameIndex = $tokens->getNextMeaningfulToken($functionIndex);
            $functionName = $tokens[$functionNameIndex]->getContent();
            //ignore functions that start with test
            if ($this->startsWith('test', $functionName)) {
                continue;
            }
            $functionName = ucfirst($functionName);
            $newFunctionName = 'test'.$functionName;
            $tokens->offsetSet($functionNameIndex, new Token([T_STRING, $newFunctionName]));
        }
    }

    /**
     * @param string $needle
     * @param string $haystack
     *
     * @return bool
     */
    private function startsWith($needle, $haystack)
    {
        $len = strlen($needle);

        return substr($haystack, 0, $len) === $needle;
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
     * @param int    $index
     *
     * @return string
     */
    private function detectIndent(Tokens $tokens, $index)
    {
        if (!$tokens[$index - 1]->isWhitespace()) {
            return ''; // cannot detect indent
        }

        $explodedContent = explode("\n", $tokens[$index - 1]->getContent());

        return end($explodedContent);
    }
}
