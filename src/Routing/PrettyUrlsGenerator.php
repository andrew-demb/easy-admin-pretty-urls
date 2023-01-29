<?php

declare(strict_types=1);

namespace MarcinJozwikowski\EasyAdminPrettyUrls\Routing;

use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;

class PrettyUrlsGenerator implements UrlGeneratorInterface
{
    public const EA_FQCN = 'crudControllerFqcn';
    public const EA_ACTION = 'crudAction';
    public const EA_MENU_INDEX = 'menuIndex';
    public const EA_SUBMENU_INDEX = 'submenuIndex';
    public const MENU_PATH = 'menuPath';

    public function __construct(
        private RouterInterface $router,
        private LoggerInterface $logger,
        private string $prettyUrlsRoutePrefix,
        private bool $prettyUrlsIncludeMenuIndex,
    ) {
    }

    public function setContext(RequestContext $context)
    {
        $this->router->setContext($context);
    }

    public function getContext(): RequestContext
    {
        return $this->router->getContext();
    }

    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        if (isset($parameters[static::EA_FQCN]) && isset($parameters[static::EA_ACTION])) {
            $prettyName = $this->generateNameFromParameters($parameters);
            $prettyParams = $parameters;
            unset($prettyParams[static::EA_FQCN]);
            unset($prettyParams[static::EA_ACTION]);

            if ($this->prettyUrlsIncludeMenuIndex && $menuIndex = $this->generateMenuIndexPart($parameters)) {
                unset($prettyParams[static::EA_MENU_INDEX]);
                unset($prettyParams[static::EA_SUBMENU_INDEX]);
                $prettyParams[self::MENU_PATH] = $menuIndex;
            }

            try {
                return $this->router->generate($prettyName, $prettyParams, $referenceType);
            } catch (RouteNotFoundException $e) {
                $this->logger->debug('Pretty route not found', [
                    'route_name' => $prettyName,
                    static::EA_FQCN => $parameters[static::EA_FQCN],
                    static::EA_ACTION => $parameters[static::EA_ACTION],
                ]);
            }
        }

        return $this->router->generate($name, $parameters, $referenceType);
    }

    private function generateNameFromParameters(array $parameters): string
    {
        $classNameA = explode('\\', $parameters[static::EA_FQCN]);
        $className = end($classNameA);
        $className = str_replace(['Controller', 'Crud'], ['', ''], $className);
        $routeName = strtolower(preg_replace('/[A-Z]/', '_\\0', lcfirst($className)));

        return sprintf('%s_%s_%s', $this->prettyUrlsRoutePrefix, $routeName, strtolower($parameters[static::EA_ACTION]));
    }

    private function generateMenuIndexPart(array $parameters): ?string
    {
        return sprintf(
            '%d,%d',
            $parameters[self::EA_MENU_INDEX] ?? -1,
            $parameters[self::EA_SUBMENU_INDEX] ?? -1,
        );
    }
}
