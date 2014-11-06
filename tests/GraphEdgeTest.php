<?php

use SammyK\FacebookQueryBuilder\GraphEdge;

class GraphEdgeTest extends PHPUnit_Framework_TestCase
{
    /** @test */
    public function the_edge_can_instantiate_with_just_the_edge_name()
    {
        $edge = new GraphEdge('foo');

        $this->assertInstanceOf('SammyK\FacebookQueryBuilder\GraphEdge', $edge);
    }

    /** @test */
    public function the_limit_gets_set_properly()
    {
        $edge = new GraphEdge('foo');
        $edge->limit(5);

        $this->assertEquals(5, $edge->getLimit());
    }

    /** @test */
    public function the_fields_can_be_set_by_sending_an_array()
    {
        $edge = new GraphEdge('foo');
        $edge->fields(['bar', 'baz']);

        $this->assertEquals(['bar', 'baz'], $edge->getFields());
    }

    /** @test */
    public function the_fields_can_be_set_from_constructor_arguments()
    {
        $edge = new GraphEdge('foo');
        $edge->fields('bar', 'baz');

        $this->assertEquals(['bar', 'baz'], $edge->getFields());
    }

    /** @test */
    public function new_fields_will_get_merged_into_existing_fields()
    {
        $edge = new GraphEdge('foo', ['foo', 'bar']);
        $edge->fields('baz');

        $this->assertEquals(['foo', 'bar', 'baz'], $edge->getFields());
    }

    /** @test */
    public function the_modifiers_can_be_set_by_sending_an_array()
    {
        $edge = new GraphEdge('foo');
        $edge->with(['bar' => 'baz']);

        $this->assertEquals(['bar' => 'baz'], $edge->getModifiers());
    }

    /** @test */
    public function modifiers_get_compiled_with_proper_syntax()
    {
        $edge = new GraphEdge('foo');
        $modifiers = $edge->compileModifiers();
        $this->assertEquals('', $modifiers);

        $edge2 = new GraphEdge('bar');
        $edge2->with(['bar' => 'baz']);
        $modifiers2 = $edge2->compileModifiers();
        $this->assertEquals('.bar(baz)', $modifiers2);

        $edge3 = new GraphEdge('baz');
        $edge3->with([
                'foo' => 'bar',
                'faz' => 'baz',
            ]);
        $modifiers3 = $edge3->compileModifiers();
        $this->assertEquals('.foo(bar).faz(baz)', $modifiers3);
    }

    /** @test */
    public function an_edge_will_convert_to_string()
    {
        $edge = new GraphEdge('foo');

        $this->assertEquals('foo', (string) $edge);
    }

    /** @test */
    public function an_edge_with_fields_will_convert_to_string()
    {
        $edge_one = new GraphEdge('foo', ['bar']);
        $edge_two = new GraphEdge('foo', ['bar', 'baz']);

        $this->assertEquals('foo{bar}', (string) $edge_one);
        $this->assertEquals('foo{bar,baz}', (string) $edge_two);
    }

    /** @test */
    public function an_edge_with_fields_and_limit_will_convert_to_string()
    {
        $edge = new GraphEdge('foo', ['bar', 'baz'], 3);

        $this->assertEquals('foo.limit(3){bar,baz}', (string) $edge);
    }

    /** @test */
    public function an_edge_with_fields_and_limit_and_modifiers_will_convert_to_string()
    {
        $edge = new GraphEdge('foo', ['bar', 'baz'], 3);
        $edge->with(['foo' => 'bar']);

        $this->assertEquals('foo.limit(3){bar,baz}.foo(bar)', (string) $edge);
    }

    /** @test */
    public function an_edge_can_be_embedded_into_another_edge()
    {
        $edge_to_embed = new GraphEdge('embeds', ['faz', 'boo'], 6);
        $edge = new GraphEdge('foo', ['bar', 'baz', $edge_to_embed], 3);

        $this->assertEquals('foo.limit(3){bar,baz,embeds.limit(6){faz,boo}}', (string) $edge);
    }

    /** @test */
    public function edges_can_be_embedded_into_other_edges_deeply()
    {
        $edge_level_one = new GraphEdge('level_one', ['one', 'foo'], 1);
        $edge_level_two = new GraphEdge('level_two', ['two', 'bar', $edge_level_one], 2);
        $edge_level_three = new GraphEdge('level_three', ['three', 'baz', $edge_level_two], 3);
        $edge_level_four = new GraphEdge('level_four', ['four', 'faz', $edge_level_three], 4);
        $edge = new GraphEdge('root', ['foo', 'bar', $edge_level_four], 5);

        $expected_one = 'level_one.limit(1){one,foo}';
        $expected_two = 'level_two.limit(2){two,bar,' . $expected_one .'}';
        $expected_three = 'level_three.limit(3){three,baz,' . $expected_two .'}';
        $expected_four = 'level_four.limit(4){four,faz,' . $expected_three .'}';
        $expected_edge = 'root.limit(5){foo,bar,' . $expected_four .'}';

        $this->assertEquals($expected_edge, (string) $edge);
    }

    /** @test */
    public function multiple_edges_can_be_embedded_into_other_edges_deeply()
    {
        $edge_tags = new GraphEdge('tags', [], 2);

        $edge_d = new GraphEdge('d');
        $edge_c = new GraphEdge('c', [$edge_d]);
        $edge_b = new GraphEdge('b', [$edge_c, $edge_tags]);
        $edge_a = new GraphEdge('a', [$edge_b]);

        $edge_four = new GraphEdge('four', ['one', 'foo'], 4);
        $edge_three = new GraphEdge('three', [$edge_four, 'bar', $edge_a], 3);
        $edge_two = new GraphEdge('two', [$edge_three], 2);
        $edge_one = new GraphEdge('one', ['faz', $edge_two]);
        $edge = new GraphEdge('root', ['foo', 'bar', $edge_one]);

        // Expected output
        $expected_tags = 'tags.limit(2)';

        $expected_d = 'd';
        $expected_c = 'c{' . $expected_d . '}';
        $expected_b = 'b{' . $expected_c . ',' . $expected_tags . '}';
        $expected_a = 'a{' . $expected_b . '}';

        $expected_four = 'four.limit(4){one,foo}';
        $expected_three = 'three.limit(3){' . $expected_four .',bar,' . $expected_a .'}';
        $expected_two = 'two.limit(2){' . $expected_three .'}';
        $expected_one = 'one{faz,' . $expected_two .'}';
        $expected_edge = 'root{foo,bar,' . $expected_one .'}';

        $this->assertEquals($expected_edge, (string) $edge);
    }
}