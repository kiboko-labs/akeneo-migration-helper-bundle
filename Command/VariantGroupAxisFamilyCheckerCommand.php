<?php


namespace Kiboko\AkeneoMigrationHelperBundle\Command;


use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VariantGroupAxisFamilyCheckerCommand extends MigrationHelperCommand
{
    public function configure()
    {
        $this
            ->setName('kiboko:migration-helper:check-variant-group-axis-family')
            ->setDescription('Look for variants groups that have an axis attribute that does not belong to the variant family')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $stmt = $this->getStmt();
        $data = [];
        while ($row = $stmt->fetch()) {
            $data[] = array_reverse($row);
        }
        if (count($data) > 0) {
            $output->writeln('<error>Some axis attributes should be added to the variant family attributes.</error>');
            $this->displayResultsInATable($output, [
                'headers' => ['attribute_code', 'family_code'],
                'rows' => $data,
            ]);
            $output->writeln(sprintf(
                '<comment>(e.g. the attribute with code "%s" should be added to the attribute list of the family "%s".)</comment>',
                $data[0]['attribute_code'],
                $data[0]['family_code']
            ));
        } else {
            $output->writeln('<info>Everything is ok !</info>');
        }
    }

    protected function getSql()
    {
        return <<<SQL
select
       pcf.code as family_code,
       a.code as attribute_code
from pim_catalog_attribute a
join pim_catalog_group_attribute pcga on a.id = pcga.attribute_id
join pim_catalog_group_product pcgp on pcgp.group_id = pcga.group_id
join pim_catalog_product pcp on pcgp.product_id = pcp.id
join pim_catalog_family pcf on pcf.id = pcp.family_id
left join pim_catalog_family_attribute pcfa on a.id = pcfa.attribute_id and pcfa.family_id = pcp.family_id
where pcfa.attribute_id is null
group by pcf.code, a.code;
SQL;
    }
}
