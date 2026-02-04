<?php declare(strict_types=1);

namespace RatMD\Laika\Twig;

use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

class ViteTokenParser extends AbstractTokenParser
{
    /**
     * Parses a token and returns a node.
     * @return PageNode
     */
    public function parse(Token $token)
    {
        $stream = $this->parser->getStream();

        $entrypoints = [];
        $buildDir = null;

        // Fetch entry points
        $stream->expect(Token::OPERATOR_TYPE, '(');

        if ($stream->test(Token::OPERATOR_TYPE, '[')) {
            $stream->next();
            while(!$stream->test(Token::PUNCTUATION_TYPE, ']')) {
                $entrypoints[] = $stream->expect(Token::STRING_TYPE)->getValue();
                if ($stream->test(Token::PUNCTUATION_TYPE, ',')) {
                    $stream->next();
                }
            }
            $stream->next();
        } else {
            $entrypoints[] = $stream->next()->getValue();
        }

        // Fetch build directory
        if ($stream->test(Token::PUNCTUATION_TYPE, ',')) {
            $stream->next();
            $buildDir = $stream->expect(Token::STRING_TYPE)->getValue();
        }

        $stream->expect(Token::PUNCTUATION_TYPE, ')');
        $stream->expect(Token::BLOCK_END_TYPE);
        return new TokenNode($token->getLine(), $this->getTag(), [
            'entrypoints'   => $entrypoints,
            'buildDir'      => $buildDir,
        ]);
    }

    /**
     * Gets the tag name associated with this token parser.
     * @return string
     */
    public function getTag()
    {
        return 'vite';
    }
}
