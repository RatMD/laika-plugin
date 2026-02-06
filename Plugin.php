<?php declare(strict_types=1);

namespace RatMD\Laika;

use Cms\Classes\ComponentBase;
use Cms\Classes\Controller;
use Cms\Classes\Page;
use Cms\Classes\Theme;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use October\Rain\Support\Facades\Event;
use RatMD\Laika\Classes\LaikaFactory;
use RatMD\Laika\Classes\PayloadBuilder;
use RatMD\Laika\Twig\Extension;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use System\Classes\PluginBase;
use Twig\Environment;

/**
 * Plugin Information File
 * @link https://docs.octobercms.com/3.x/extend/system/plugins.html
 */
class Plugin extends PluginBase
{
    /**
     * Required plugin dependencies.
     * @var array
     */
    public $require = [ ];

    /**
     * @inheritDoc
     */
    public function pluginDetails()
    {
        return [
            'name'          => 'Laika',
            'description'   => 'LAIKA is an Inertia-inspired Vue/Vite adapter which lets you build your entire OctoberCMS theme in Vue while still using everything October provides.',
            'author'        => 'rat.md',
            'icon'          => 'icon-paw'
        ];
    }

    /**
     * @inheritDoc
     */
    public function register()
    {
        $this->app->singleton(LaikaFactory::class);

        $this->app->singleton(Vite::class, function () {
            $theme = Theme::getActiveTheme()->getDirName();
            $vite = new Vite;
            $vite->useBuildDirectory(themes_path("{$theme}/assets/build"));
            $vite->useHotFile(themes_path("{$theme}/assets/.hot"));
            return $vite;
        });
    }

    /**
     * @inheritDoc
     */
    public function boot()
    {
        // @todo find a better solution to receive the component properties.
        //       using multiple [staticMenu alias] components overwrites the pageObj
        ComponentBase::extend(function($component) {
            $component->addDynamicMethod('getPageVars', function() use ($component) {
                $ref = new \ReflectionObject($component);
                while ($ref) {
                    if ($ref->hasProperty('page')) {
                        $page = $ref->getProperty('page');
                        $pageObj = $page->getValue($component);
                        break;
                    }
                    $ref = $ref->getParentClass();
                }

                if (!isset($pageObj) || !is_object($pageObj)) {
                    return [];
                } else {
                    $vars = json_decode(json_encode($pageObj->vars), true);
                    $keys = array_keys($pageObj->vars);

                    $ref = new \ReflectionObject($component);
                    foreach ($keys AS $key) {
                        if ($key === 'page') {
                            continue;
                        }
                        if ($ref->hasProperty($key)) {
                            $val = $ref->getProperty($key);
                            $vars[$key] = $val->getValue($component);
                        }
                    }

                    return $vars;
                }
            });
        });

        Event::listen('cms.extendTwig', function (Environment $twig) {
            $twig->addExtension(new Extension);
        });

        Event::listen('cms.page.display', function (Controller $controller, string $url, Page $page, $result) {
            if (!Request::header('X-Laika')) {
                return null;
            }
            if ($result instanceof SymfonyResponse) {
                return $this->transformSymfonyResponse($result);
            }

            $request = $controller->getAjaxRequest();
            if ($request->hasAjaxHandler()) {
                return null;
            }

            // Fetch Page Content
            $oldLayout = $page->layout;
            $page->layout = null;
            $pageContent = $controller->renderPage();
            $page->layout = $oldLayout;

            // Laika Response
            $builder = PayloadBuilder::fromController($controller, $page);
            $builder->setPageContent($pageContent);
            $payload = $builder->toArray();
            return Response::json($payload, 200, [
                'Vary'      => 'X-Laika',
                'X-Laika'   => '1',
            ]);
        });
    }

    /**
     *
     * @param SymfonyResponse $response
     * @return SymfonyResponse
     */
    protected function transformSymfonyResponse(SymfonyResponse $response): SymfonyResponse
    {
        if ($response->isRedirection()) {
            $location = $response->headers->get('Location');

            return response('', 409, [
                'X-Laika-Location' => $location,
                'X-Laika' => '1',
            ]);
        }

        return $response;
    }
}
