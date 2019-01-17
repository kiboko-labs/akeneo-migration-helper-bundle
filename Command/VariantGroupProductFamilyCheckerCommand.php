<?php


namespace Kiboko\AkeneoMigrationHelperBundle\Command;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VariantGroupProductFamilyCheckerCommand extends MigrationHelperCommand
{
    public function configure()
    {
        $this
            ->setName('kiboko:migration-helper:check-variant-groups-product-families')
            ->setDescription('Check that all products in a variant group have the same family')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $stmt = $this->getStmt();
        $data = [];
        while ($row = $stmt->fetch()) {
            $products = $this->formatList($row['product_gencodes']);

            $data[] = [
                'group_code' => $row['group_code'],
                'products' => $products,
            ];
        }

        if (count($data) > 0) {
            $this->displayResultsInATable($output, [
                'headers' => ['group_code', 'gencode:family_code'],
                'rows' => $data
            ], true);
            $output->writeln('<error>Some variant groups have products from different families.</error>');
        } else {
            $output->writeln('<info>Everything is OK !</info>');
        }
    }

    protected function getSql()
    {
        return <<<SQL
select
       GROUP_CONCAT(v.value_string, ':', f.code) as product_gencodes,
       g.code as group_code
from pim_catalog_group g
join pim_catalog_group_product gp on g.id = gp.group_id
join pim_catalog_product p on p.id = gp.product_id
   join pim_catalog_product_value v on v.entity_id = p.id and v.attribute_id=1
join pim_catalog_family f on f.id = p.family_id
group by g.code
having count(DISTINCT f.code) > 1
;
SQL;

    }
}
