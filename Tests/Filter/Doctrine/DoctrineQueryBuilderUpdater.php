<?php

namespace Lexik\Bundle\FormFilterBundle\Tests\Filter\Doctrine;

use Lexik\Bundle\FormFilterBundle\Filter\FilterOperands;

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RegisterKernelListenersPass;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use Lexik\Bundle\FormFilterBundle\DependencyInjection\LexikFormFilterExtension;
use Lexik\Bundle\FormFilterBundle\DependencyInjection\Compiler\FilterTransformerCompilerPass;
use Lexik\Bundle\FormFilterBundle\Filter\Extension\Type\NumberFilterType;
use Lexik\Bundle\FormFilterBundle\Filter\Extension\Type\TextFilterType;
use Lexik\Bundle\FormFilterBundle\Filter\Extension\Type\BooleanFilterType;
use Lexik\Bundle\FormFilterBundle\Filter\Transformer\TransformerAggregator;
use Lexik\Bundle\FormFilterBundle\Filter\QueryBuilderUpdater;
use Lexik\Bundle\FormFilterBundle\Tests\TestCase;
use Lexik\Bundle\FormFilterBundle\Tests\Fixtures\Filter\EmbedFilterType;
use Lexik\Bundle\FormFilterBundle\Tests\Fixtures\Filter\RangeFilterType;
use Lexik\Bundle\FormFilterBundle\Tests\Fixtures\Filter\ItemCallbackFilterType;
use Lexik\Bundle\FormFilterBundle\Tests\Fixtures\Filter\ItemFilterType;

/**
 * Filter query builder tests.
 */
abstract class DoctrineQueryBuilderUpdater extends TestCase
{
    protected function createBuildQueryTest($method, array $dqls)
    {
        $form = $this->formFactory->create(new ItemFilterType());
        $filterQueryBuilder = $this->initQueryBuilder();

        // without binding the form
        $doctrineQueryBuilder = $this->createDoctrineQueryBuilder();

        $filterQueryBuilder->addFilterConditions($form, $doctrineQueryBuilder);
        $this->assertEquals($dqls[0], $doctrineQueryBuilder->{$method}());


        // bind a request to the form - 1 params
        $doctrineQueryBuilder = $this->createDoctrineQueryBuilder();
        $form->bind(array('name' => 'blabla', 'position' => ''));

        $filterQueryBuilder->addFilterConditions($form, $doctrineQueryBuilder);
        $this->assertEquals($dqls[1], $doctrineQueryBuilder->{$method}());


        // bind a request to the form - 2 params
        $form = $this->formFactory->create(new ItemFilterType());

        $doctrineQueryBuilder = $this->createDoctrineQueryBuilder();
        $form->bind(array('name' => 'blabla', 'position' => 2));

        $filterQueryBuilder->addFilterConditions($form, $doctrineQueryBuilder);
        $this->assertEquals($dqls[2], $doctrineQueryBuilder->{$method}());


        // bind a request to the form - 3 params
        $form = $this->formFactory->create(new ItemFilterType());

        $doctrineQueryBuilder = $this->createDoctrineQueryBuilder();
        $form->bind(array('name' => 'blabla', 'position' => 2, 'enabled' => BooleanFilterType::VALUE_YES));

        $filterQueryBuilder->addFilterConditions($form, $doctrineQueryBuilder);
        $this->assertEquals($dqls[3], $doctrineQueryBuilder->{$method}());


        // bind a request to the form - 3 params (use checkbox for enabled field)
        $form = $this->formFactory->create(new ItemFilterType(false, true));

        $doctrineQueryBuilder = $this->createDoctrineQueryBuilder();
        $form->bind(array('name' => 'blabla', 'position' => 2, 'enabled' => 'yes'));

        $filterQueryBuilder->addFilterConditions($form, $doctrineQueryBuilder);
        $this->assertEquals($dqls[4], $doctrineQueryBuilder->{$method}());


        // bind a request to the form - date + pattern selector
        $form = $this->formFactory->create(new ItemFilterType(true));

        $doctrineQueryBuilder = $this->createDoctrineQueryBuilder();
        $form->bind(array(
            'name' => array('text' => 'blabla', 'condition_pattern' => FilterOperands::STRING_ENDS),
            'position' => array('text' => 2, 'condition_operator' => FilterOperands::OPERATOR_LOWER_THAN_EQUAL),
            'createdAt' => array('year' => 2013, 'month' => 9, 'day' => 27),
        ));

        $filterQueryBuilder->addFilterConditions($form, $doctrineQueryBuilder);
        $this->assertEquals($dqls[5], $doctrineQueryBuilder->{$method}());
    }

    protected function createApplyFilterOptionTest($method, array $dqls)
    {
        $form = $this->formFactory->create(new ItemCallbackFilterType());
        $filterQueryBuilder = $this->initQueryBuilder();

        $doctrineQueryBuilder = $this->createDoctrineQueryBuilder();
        $form->bind(array('name' => 'blabla', 'position' => 2));

        $filterQueryBuilder->addFilterConditions($form, $doctrineQueryBuilder);
        $this->assertEquals($dqls[0], $doctrineQueryBuilder->{$method}());
    }

    protected function createNumberRangeTest($method, array $dqls)
    {
        // use filter type options
        $form = $this->formFactory->create(new RangeFilterType());
        $filterQueryBuilder = $this->initQueryBuilder();

        $doctrineQueryBuilder = $this->createDoctrineQueryBuilder();
        $form->bind(array('position' => array('left_number' => 1, 'right_number' => 3)));

        $filterQueryBuilder->addFilterConditions($form, $doctrineQueryBuilder);
        $this->assertEquals($dqls[0], $doctrineQueryBuilder->{$method}());
    }

    protected function createNumberRangeDefaultValuesTest($method, array $dqls)
    {
        // use filter type options
        $form = $this->formFactory->create(new RangeFilterType());
        $filterQueryBuilder = $this->initQueryBuilder();

        $doctrineQueryBuilder = $this->createDoctrineQueryBuilder();
        $form->bind(array('default_position' => array('left_number' => 1, 'right_number' => 3)));

        $filterQueryBuilder->addFilterConditions($form, $doctrineQueryBuilder);
        $this->assertEquals($dqls[0], $doctrineQueryBuilder->{$method}());
    }

    protected function createDateRangeTest($method, array $dqls)
    {
        // use filter type options
        $form = $this->formFactory->create(new RangeFilterType());
        $filterQueryBuilder = $this->initQueryBuilder();

        $doctrineQueryBuilder = $this->createDoctrineQueryBuilder();
        $form->bind(array(
            'createdAt' => array(
                'left_date' => array('year' => '2012', 'month' => '5', 'day' => '12'),
                'right_date' => array('year' => '2012', 'month' => '5', 'day' => '22'),
            ),
        ));

        $filterQueryBuilder->addFilterConditions($form, $doctrineQueryBuilder);
        $this->assertEquals($dqls[0], $doctrineQueryBuilder->{$method}());
    }

    protected function initQueryBuilder()
    {
        $container = $this->getContainer();

        return $container->get('lexik_form_filter.query_builder_updater');
    }

    protected function getContainer()
    {
        $container = new ContainerBuilder();
        $filter = new LexikFormFilterExtension();
        $container->registerExtension($filter);

        $loadXml = new XmlFileLoader($container, new FileLocator(__DIR__.'/../../../vendor/symfony/src/Symfony/Bundle/FrameworkBundle/Resources/config'));
        $loadXml->load('services.xml');

        $loadXml = new XmlFileLoader($container, new FileLocator(__DIR__.'/../../../Resources/config'));
        $loadXml->load('services.xml');
        $loadXml->load('form_types.xml');
        $loadXml->load('doctrine/orm/filters.xml');

        $container->getCompilerPassConfig()->setOptimizationPasses(array());
        $container->getCompilerPassConfig()->setRemovingPasses(array());
        $container->addCompilerPass(new RegisterKernelListenersPass());
        $container->addCompilerPass(new FilterTransformerCompilerPass());
        $container->compile();

        return $container;
    }
}