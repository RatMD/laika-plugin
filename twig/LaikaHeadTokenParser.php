<?php declare(strict_types=1);

namespace RatMD\Laika\Twig;

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

class LaikaHeadTokenParser extends AbstractTokenParser
{
    /**
     * Parses a token and returns a node.
     * @return PageNode
     */
    public function parse(Token $token)
    {
        $stream = $this->parser->getStream();
        $stream->expect(Token::BLOCK_END_TYPE);

        return new TokenNode($token->getLine(), $this->getTag());
    }

    /**
     * Gets the tag name associated with this token parser.
     * @return string
     */
    public function getTag()
    {
        return 'laikaHead';
    }
}
