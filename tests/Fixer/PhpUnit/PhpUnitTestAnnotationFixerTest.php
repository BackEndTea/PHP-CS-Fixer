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
    public function testFix($expected, $input = null, array $config = [])
    {
        $this->fixer->configure($config);
        $this->doTest($expected, $input);
    }

    /**
     * @return array
     */
    public function provideFixCases()
    {
        return [
            'Annotation is used, and should be' => [
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     */
    public function itDoesSomething() {}
    }',
                null,
                ['style' => 'annotation'],
            ],
            'Annotation is used, and it should not be' => [
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     */
    public function testItDoesSomething() {}
    }',
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     */
    public function itDoesSomething() {}
    }',
                ['style' => 'prefix'],
            ],
            'Annotation is not used, but should be' => [
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
}', ['style' => 'annotation'],
            ],
            'Annotation is not used, but should be, and there is already a docBlcok' => [
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     * @dataProvider blabla
     */
    public function itDoesSomething() {}
    }',
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @dataProvider blabla
     */
    public function testItDoesSomething() {}
    }',
                ['style' => 'annotation'],
            ],
            'Annotation is not used, and should not be' => [
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    public function testItDoesSomething() {}
    }',
            ],
            'Annotation is used, but should not be, and it depends on other tests' => [
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     */
    public function testAaa () {}

    public function helperFunction() {}

    /**
     * @depends testAaa
     */
    public function testBbb () {}
}',
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     */
    public function aaa () {}

    public function helperFunction() {}

    /**
     * @test
     * @depends aaa
     */
    public function bbb () {}
}',
                ['style' => 'prefix'],
            ],
            'Annotation is not used, but should be, and it depends on other tests' => [
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     */
    public function aaa () {}

    /**
     * @test
     * @depends aaa
     */
    public function bbb () {}
}',
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    public function testAaa () {}

    /**
     * @depends testAaa
     */
    public function testBbb () {}
}',
                ['style' => 'annotation'],
            ],
            'Annotation is removed, the function is one word and we want it to use camel case' => [
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     */
    public function testWorks() {}
}',
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     */
    public function works() {}
}',
            ],
            'Annotation is removed, the function is one word and we want it to use snake case' => [
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     */
    public function test_works() {}
}',
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     */
    public function works() {}
}',
                ['case' => 'snake'],
                ],
            'Annotation is added, and it is snake case' => [
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     */
    public function it_has_snake_case() {}
}',
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    public function test_it_has_snake_case() {}
}', ['style' => 'annotation'],
            ],
            'Annotation is removed, and it is snake case' => [
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     */
    public function test_it_has_snake_case() {}

    public function helper_function() {}
}',
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     */
    public function it_has_snake_case() {}

    public function helper_function() {}
}',
                ['case' => 'snake'],
            ],
        ];
    }
}
