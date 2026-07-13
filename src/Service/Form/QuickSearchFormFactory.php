<?php declare(strict_types=1);

namespace Log\Service\Form;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Log\Form\QuickSearchForm;
use Psr\Container\ContainerInterface;

class QuickSearchFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new QuickSearchForm(null, $options ?? []);
    }
}
