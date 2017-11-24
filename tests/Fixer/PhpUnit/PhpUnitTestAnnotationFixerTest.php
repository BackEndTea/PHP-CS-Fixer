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

namespace PhpCsFixer\Tests\Fixer\PhpUnit;

use PhpCsFixer\Tests\Test\AbstractFixerTestCase;

/**
 * @author Gert de Pagter
 *
 * @internal
 *
 * @covers \PhpCsFixer\Fixer\PhpUnit\PhpUnitTestAnnotationFixer
 */
final class PhpUnitTestAnnotationFixerTest extends AbstractFixerTestCase
{
    /**
     * @dataProvider provideFixCases
     *
     * @param mixed $expected
     * @param mixed $input
     */
    public function testFix($expected, $input, array $config)
    {
        $this->fixer->configure($config);
        $this->doTest($expected, $input);
    }

    /**
     * Case 1: Notation present, and wanted
     * Case 2: Notation present, and not wanted
     * Case 3: Notation not present, but wanted
     * Case 4: Notation not present, but wanted, and there is already a notation
     * Case 4: Notation not present, and not wanted
     *
     * @return array
     */
    public function provideFixCases()
    {
        return [
            ['<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     */
    public function itDoesSomething() {}
    }', null, [],
            ],
            ['<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     */
    public function testItDoesSomething() {}
    }', '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     */
    public function itDoesSomething() {}
    }', ['annotation' => false],
            ],
            [
'<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     */
    public function itDoesSomething() {}
}',
'<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    public function testItDoesSomething() {}
}', ['annotation' => true],
            ],
            ['<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     * @dataProvider blabla
     */
    public function itDoesSomething() {}
    }', '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @dataProvider blabla
     */
    public function testItDoesSomething() {}
    }', []
            ],
            ['<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    public function testItDoesSomething() {}
    }', null, ['annotation' => false]
            ]
        ];
    }
}
