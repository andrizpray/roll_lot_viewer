<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\DescriptionParser;

class DescriptionParserTest extends TestCase
{
    protected DescriptionParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DescriptionParser();
    }

    public function test_parse_standard_description(): void
    {
        // 5 kata standar
        $result = $this->parser->parse('Paper Medium MP150 E150 1000');
        
        $this->assertNotNull($result);
        $this->assertEquals('Paper Medium', $result['papertype']);
        $this->assertEquals('MP150', $result['gramature']);
        $this->assertEquals('E150', $result['playbond']);
        $this->assertEquals('1000', $result['width']);
    }

    public function test_parse_multi_word_papertype(): void
    {
        // Papertype dengan 2+ kata
        $result = $this->parser->parse('PE B Kraft BRP290 E150 950');
        
        $this->assertNotNull($result);
        $this->assertEquals('PE B Kraft', $result['papertype']);
        $this->assertEquals('BRP290', $result['gramature']);
        $this->assertEquals('E150', $result['playbond']);
        $this->assertEquals('950', $result['width']);
    }

    public function test_parse_complex_papertype(): void
    {
        // Papertype sangat panjang (6 kata)
        $result = $this->parser->parse('BPTB B Kraft PE T/B BPTB325 E150 900');
        
        $this->assertNotNull($result);
        $this->assertEquals('BPTB B Kraft PE T/B', $result['papertype']);
        $this->assertEquals('BPTB325', $result['gramature']);
        $this->assertEquals('E150', $result['playbond']);
        $this->assertEquals('900', $result['width']);
    }

    public function test_parse_no_playbond_dash(): void
    {
        // Playbond "-". Jadi null
        $result = $this->parser->parse('JR 1 B KRAFT JRBK200 - 3350');
        
        $this->assertNotNull($result);
        $this->assertEquals('JR 1 B KRAFT', $result['papertype']);
        $this->assertEquals('JRBK200', $result['gramature']);
        $this->assertNull($result['playbond']);
        $this->assertEquals('3350', $result['width']);
    }

    public function test_parse_7_words(): void
    {
        // 7 kata
        $result = $this->parser->parse('PE B Kraft PE T/B BPTB300 E150 1200');
        
        $this->assertNotNull($result);
        $this->assertEquals('PE B Kraft PE T/B', $result['papertype']);
        $this->assertEquals('BPTB300', $result['gramature']);
        $this->assertEquals('E150', $result['playbond']);
        $this->assertEquals('1200', $result['width']);
    }

    public function test_parse_8_words(): void
    {
        // 8 kata
        $result = $this->parser->parse('Paper Medium White MP150 E150 1000');
        
        $this->assertNotNull($result);
        $this->assertEquals('Paper Medium White', $result['papertype']);
        $this->assertEquals('MP150', $result['gramature']);
        $this->assertEquals('E150', $result['playbond']);
        $this->assertEquals('1000', $result['width']);
    }

    public function test_parse_9_words(): void
    {
        // 9 kata - format valid sesuai aturan parsing
        $result = $this->parser->parse('BPTB B Kraft PE T/B Extra BPTB325 E150 900');
        
        $this->assertNotNull($result);
        $this->assertEquals('BPTB B Kraft PE T/B Extra', $result['papertype']);
        $this->assertEquals('BPTB325', $result['gramature']);
        $this->assertEquals('E150', $result['playbond']);
        $this->assertEquals('900', $result['width']);
    }

    public function test_parse_too_few_words(): void
    {
        // Hanya 3 kata - invalid
        $result = $this->parser->parse('Paper Medium MP150');
        
        $this->assertNull($result);
    }

    public function test_parse_empty_string(): void
    {
        // Empty string
        $result = $this->parser->parse('');
        
        $this->assertNull($result);
    }

    public function test_parse_batch_with_mixed_valid_invalid(): void
    {
        $descriptions = [
            'Paper Medium MP150 E150 1000',      // valid
            '',                                  // invalid (empty)
            'Short',                             // invalid (< 4 kata)
            'PE B Kraft BRP290 E150 950',        // valid
            'JR 1 B KRAFT JRBK200 - 3350',       // valid (playbond null)
        ];
        
        $result = $this->parser->parseBatch($descriptions);
        
        $this->assertCount(3, $result['valid']);
        $this->assertCount(2, $result['invalid']);
        
        $this->assertEquals('Paper Medium', $result['valid'][0]['papertype']);
        $this->assertEquals('PE B Kraft', $result['valid'][1]['papertype']);
        $this->assertNull($result['valid'][2]['playbond']);
        
        $this->assertEquals('Empty description', $result['invalid'][0]['reason']);
        $this->assertStringContainsString('less than 4 words', $result['invalid'][1]['reason']);
    }
}
