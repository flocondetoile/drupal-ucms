<?php

namespace MakinaCorpus\Ucms\Contrib\Tests;

use MakinaCorpus\Drupal\Sf\Tests\AbstractDrupalTest;

class ContentTypeManagerTest extends AbstractDrupalTest
{
    protected function setUp()
    {
        parent::setUp();
    }

    /**
     * Determine if two associative arrays are similar
     *
     * Both arrays must have the same indexes with identical values
     * without respect to key ordering
     *
     * Taken from http://stackoverflow.com/a/3843768/848811
     *
     * @param array $a
     * @param array $b
     * @return bool
     */
    public function assertArrayAreSimilar($a, $b)
    {
        // if the indexes don't match, return immediately
        if (count(array_diff_assoc($a, $b))) {
            return false;
        }
        // we know that the indexes, but maybe not values, match.
        // compare the values between the two arrays
        foreach ($a as $k => $v) {
            if ($v !== $b[$k]) {
                return false;
            }
        }

        // we have identical indexes, and no unequal values
        return true;
    }

    public function testContentTypeManager()
    {
        $contentTypeManager = $this->getMockBuilder('\MakinaCorpus\Ucms\Contrib\ContentTypeManager')
            ->setMethods(
                array(
                    'getEditorialTypes',
                    'getComponentTypes',
                    'getMediaTypes',
                    'getNonMediaTypes',
                    'getNonComponentTypes',
                )
            )
            ->getMock();

        // Mocking values
        $editorial = ['editorial_foo', 'editorial_bar'];
        $contentTypeManager->method('getEditorialTypes')
                    ->willReturn($editorial);
        $components = ['component_foo', 'component_bar'];
        $contentTypeManager->method('getComponentTypes')
                    ->willReturn($components);
        $contentTypeManager->method('getNonMediaTypes')
                    ->willReturn(array_merge($components, $editorial));
        $media = ['media_foo', 'media_bar'];
        $contentTypeManager->method('getMediaTypes')
                    ->willReturn($media);

        // Testing functions
        $this->assertArrayAreSimilar($contentTypeManager->getAllTypes(), array_merge($editorial, $components, $media));
        $this->assertArrayAreSimilar($contentTypeManager->getNonComponentTypes(), array_merge($editorial, $media));
        $this->assertArrayAreSimilar($contentTypeManager->getTabTypes('media'), $media);
        $this->assertArrayAreSimilar($contentTypeManager->getTabTypes('content'), array_merge($components, $editorial));

        $this->expectException(\Exception::class);
        $contentTypeManager->getTabTypes('foo');

        // @Todo test human readable names
    }
}
