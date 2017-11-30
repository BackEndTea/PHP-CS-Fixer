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
     * @param array $config
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
}',
                ['style' => 'annotation'],
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
}',
                ['style' => 'annotation'],
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
                'Annotation gets added, it has an @depends, and we use snake case' => [
                    '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     */
    public function works_fine () {}

    /**
     * @test
     * @depends works_fine
     */
    public function works_fine_too() {}
}',
                    '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    public function test_works_fine () {}

    /**
     * @depends test_works_fine
     */
    public function test_works_fine_too() {}
}',
                    ['style' => 'annotation'],
                    ],
                'Annotation gets removed, it has an @depends and we use camel case' => [
                    '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     */
    public function test_works_fine () {}

    /**
     * @depends test_works_fine
     */
    public function test_works_fine_too() {}
}',
                        '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     */
    public function works_fine () {}

    /**
     * @depends works_fine
     */
    public function works_fine_too() {}
}',
                    ['case' => 'snake'],
                ],
            'Class has both camel and snake case, annotated functions and not, and wants to add annotations' => [
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     */
    public function snake_cased () {}

    /**
     * @test
     */
    public function camelCased () {}

    /**
     * @test
     * @depends camelCased
     */
    public function depends_on_someone () {}

    //It even has a comment
    public function a_helper_function () {}

    /**
     * @test
     * @depends depends_on_someone
     */
    public function moreDepends() {}
}',
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    public function test_snake_cased () {}

    public function testCamelCased () {}

    /**
     * @depends testCamelCased
     */
    public function test_depends_on_someone () {}

    //It even has a comment
    public function a_helper_function () {}

    /**
     * @depends depends_on_someone
     */
    public function testMoreDepends() {}
}',
                ['style' => 'annotation'],
            ],
            'Annotation has to be added to multiple functions' => [
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     */
    public function itWorks() {}

    /**
     * @test
     */
    public function itDoesSomething() {}
}',
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    public function testItWorks() {}

    public function testItDoesSomething() {}
}',
                ['style' => 'annotation'],
            ],
            'Annotation has to be removed from multiple functions and we use snake case' => [
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     */
    public function test_it_works() {}

    /**
     */
    public function test_it_does_something() {}

    public function dataProvider() {}

    /**
     * @dataprovider dataProvider
     * @depends test_it_does_something
     */
    public function test_it_depend_and_has_provider() {}

}',
                '<?php
class Test extends \PhpUnit\FrameWork\TestCase
{
    /**
     * @test
     */
    public function it_works() {}

    /**
     * @test
     */
    public function it_does_something() {}

    public function dataProvider() {}

    /**
     * @dataprovider dataProvider
     * @depends it_does_something
     */
    public function test_it_depend_and_has_provider() {}

}',
                ['case' => 'snake'],
            ],
        ];
    }
}
