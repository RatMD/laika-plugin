<?php declare(strict_types=1);

namespace RatMD\Laika\Twig;

use Twig\Node\Node;
use Twig\Compiler;

#[\Twig\Attribute\YieldReady]
class TokenNode extends Node
{
    /**
     *
     * @param int $lineno
     */
    public function __construct(int $lineno, string $tagName, array $params = [])
    {
        $attributes = [
            'tagName'   => $tagName,
            'params'    => $params,
        ];
        parent::__construct([], $attributes, $lineno);
    }

    /**
     * Compiles the node to PHP.
     * @param Compiler $compiler
     * @return void
     */
    public function compile(Compiler $compiler)
    {
        $compiler->addDebugInfo($this);

        $tagName = $this->getAttribute('tagName');
        $params = $this->getAttribute('params');
        if (!empty($params)) {
            foreach ($params AS $key => $value) {
                $varId = '__laika_'. $tagName .'_' . $key . '_parameter';
                $compiler->write("\$context['" . $varId . "'] = ". var_export($value, true) .";\n");
            }
        }

        $compiler->write("yield \$this->env->getExtension(\RatMD\Laika\Twig\Extension::class)->{$tagName}Function(\$context);\n");

        if ($tagName === 'laikaHead') {
            $compiler->write("yield \$this->env->getExtension(\Cms\Twig\Extension::class)->assetsFunction('css');\n");
            $compiler->write("yield \$this->env->getExtension(\Cms\Twig\Extension::class)->displayBlock('styles');\n");
        }
        if ($tagName === 'laika') {
            $compiler->write("yield \$this->env->getExtension(\Cms\Twig\Extension::class)->assetsFunction('js');\n");
            $compiler->write("yield \$this->env->getExtension(\Cms\Twig\Extension::class)->displayBlock('scripts');\n");
        }
    }
}
