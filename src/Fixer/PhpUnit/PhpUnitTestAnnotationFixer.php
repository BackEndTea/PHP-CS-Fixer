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
use PhpCsFixer\DocBlock\Line;
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

    private $isCamelCase;

    /**
     * {@inheritdoc}
     */
    public function configure(array $configuration = null)
    {
        parent::configure($configuration);

        $this->annotated = 'annotation' === $this->configuration['style'];
        $this->isCamelCase = 'camel' === $this->configuration['case'];
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
public function testItDoesSomething() {}}\n", ['style' => 'annotation']),
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
                ->getOption(),
            (new FixerOptionBuilder('case', 'Whether to camel or snake case when adding the test prefix'))
                ->setAllowedValues(['camel', 'snake'])
                ->setDefault('camel')
                ->getOption(),
        ]);
    }

    /**
     * @param Tokens $tokens
     * @param $index
     *
     * @return bool
     */
    private function doesFunctionHaveDocBlock(Tokens $tokens, $index)
    {
        $docBlockIndex = $this->getDockBlockIndex($tokens, $index);

        return $tokens[$docBlockIndex]->isGivenKind(T_DOC_COMMENT);
    }

    private function getDockBlockIndex(Tokens $tokens, $index)
    {
        do {
            $index = $tokens->getPrevNonWhitespace($index);
        } while ($tokens[$index]->isGivenKind([T_PUBLIC, T_PROTECTED, T_PRIVATE, T_FINAL, T_ABSTRACT, T_COMMENT]));

        return $index;
    }

    /**
     * @param Tokens $tokens
     * @param $index
     *
     * @return bool
     */
    private function shouldBeChecked(Tokens $tokens, $index)
    {
        $tokensAnalyzer = new TokensAnalyzer($tokens);
        if (!$tokens[$index]->isGivenKind(T_FUNCTION) || $tokensAnalyzer->isLambda($index)) {
            return false;
        }

        // ignore abstract functions
        $braceIndex = $tokens->getNextTokenOfKind($index, [';', '{']);
        if (!$tokens[$braceIndex]->equals('{')) {
            return false;
        }

        return true;
    }

    private function applyTestAnnotation(Tokens $tokens, $startIndex, $endIndex)
    {
        for ($i = $endIndex - 1; $i > $startIndex; --$i) {
            if (!$this->shouldBeChecked($tokens, $i)) {
                continue;
            }

            $functionNameIndex = $tokens->getNextMeaningfulToken($i);
            $functionName = $tokens[$functionNameIndex]->getContent();

            //ignore functions that don't start with test
            if (!$this->startsWith('test', $functionName)) {
                continue;
            }

            $newFunctionName = $this->removeTestFromFunctionName($functionName);
            $tokens[$functionNameIndex] = new Token([T_STRING, $newFunctionName]);

            $docBlockIndex = $this->getDockBlockIndex($tokens, $i);

            //Create a new docblock if it didn't have one before;
            if (!$this->doesFunctionHaveDocBlock($tokens, $i)) {
                $this->createDocBlock($tokens, $docBlockIndex);

                continue;
            }

            $doc = new DocBlock($tokens[$docBlockIndex]->getContent());
            $lines = $this->updateDocBlock($tokens, $docBlockIndex);

            if (false === strpos($doc->getContent(), '@test')) {
                $originalIndent = $this->detectIndent($tokens, $tokens->getNextNonWhitespace($docBlockIndex));
                array_splice($lines, 1, 0, $originalIndent." * @test\n");
            }
            $lines = implode($lines);
            $tokens[$docBlockIndex] = new Token([T_DOC_COMMENT, $lines]);
        }
    }

    private function removeTestAnnotation(Tokens $tokens, $startIndex, $endIndex)
    {
        for ($i = $endIndex - 1; $i > $startIndex; --$i) {
            if (!$this->shouldBeChecked($tokens, $i) || !$this->doesFunctionHaveDocBlock($tokens, $i)) {
                continue;
            }

            $docBlockIndex = $this->getDockBlockIndex($tokens, $i);

            $lines = $this->updateDocBlock($tokens, $docBlockIndex);

            $lines = implode($lines);
            $tokens[$docBlockIndex] = new Token([T_DOC_COMMENT, $lines]);

            $functionNameIndex = $tokens->getNextMeaningfulToken($i);
            $functionName = $tokens[$functionNameIndex]->getContent();

            //if the function already starts with test were done
            if ($this->startsWith('test', $functionName)) {
                continue;
            }

            $newFunctionName = $this->addTestToFunctionName($functionName);
            $tokens[$functionNameIndex] = new Token([T_STRING, $newFunctionName]);
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

    /**
     * @param string $functionName
     *
     * @return string
     */
    private function removeTestFromFunctionName($functionName)
    {
        if ($this->startsWith('test_', $functionName)) {
            $newFunctionName = substr($functionName, 5);
        } else {
            $functionName = substr($functionName, 4);
            $newFunctionName = lcfirst($functionName);
        }

        return $newFunctionName;
    }

    /**
     * @param string $functionName
     *
     * @return string
     */
    private function addTestToFunctionName($functionName)
    {
        if (!$this->isCamelCase) {
            $newFunctionName = 'test_'.$functionName;
        } else {
            $newFunctionName = 'test'.ucfirst($functionName);
        }

        return $newFunctionName;
    }

    /**
     * @param Tokens $tokens
     * @param $docBlockIndex
     */
    private function createDocBlock(Tokens $tokens, $docBlockIndex)
    {
        $originalIndent = $this->detectIndent($tokens, $tokens->getNextNonWhitespace($docBlockIndex));
        $toInsert = [
            new Token([T_DOC_COMMENT, "/**\n$originalIndent * @test\n$originalIndent */"]),
            new Token([T_WHITESPACE, "\n".$originalIndent]),
        ];
        $index = $tokens->getNextMeaningfulToken($docBlockIndex);
        $tokens->insertAt($index, $toInsert);
    }

    /**
     * @param Tokens $tokens
     * @param $docBlockIndex
     *
     * @return Line[]
     */
    private function updateDocBlock(Tokens $tokens, $docBlockIndex)
    {
        $doc = new DocBlock($tokens[$docBlockIndex]->getContent());

        $lines = $doc->getLines();
        for ($j = 0; $j < \count($lines); ++$j) {
            //ignore lines that dont have a tag
            if (!$lines[$j]->containsATag()) {
                continue;
            }
            if (!$this->annotated) {
                if (false !== strpos($lines[$j], '@test')) {
                    $lines[$j]->remove();
                }
            }
            //ignore the line if it isnt @depends
            if (false === strpos($lines[$j], '@depends')) {
                continue;
            }
            $line = \str_split($lines[$j]->getContent());
            $counter = \count($line);

            //find the point where the function name starts
            do {
                --$counter;
            } while (' ' !== $line[$counter]);
            $dependsFunctionName = implode(array_slice($line, $counter + 1));
            //Ignore if that functions that dont start with test
            if ($this->annotated) {
                if (!$this->startsWith('test', $dependsFunctionName)) {
                    continue;
                }
                $dependsFunctionName = $this->removeTestFromFunctionName($dependsFunctionName);
                array_splice($line, $counter + 1);
                $lines[$j] = new Line(implode($line).$dependsFunctionName);

                continue;
            }
            if ($this->startsWith('test', $dependsFunctionName)) {
                continue;
            }
            $dependsFunctionName = $this->addTestToFunctionName($dependsFunctionName);

            array_splice($line, $counter + 1);
            $lines[$j] = new Line(implode($line).$dependsFunctionName);
        }

        return $lines;
    }
}
