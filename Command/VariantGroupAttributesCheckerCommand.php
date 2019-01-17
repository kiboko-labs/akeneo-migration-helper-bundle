<?php

namespace Kiboko\AkeneoMigrationHelperBundle\Command;


use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class VariantGroupAttributesCheckerCommand extends MigrationHelperCommand
{
    const ALL = 'all';

    private $inputFamilyCode;
    private $inputAxisCode;

    public function configure()
    {
        $this
            ->setName('kiboko:migration-helper:check-variant-groups-attributes')
            ->setDescription('Check the structure of the variations catalog in order to be able to migrate to Akeneo 2.0')
            ->addArgument('family_code', InputArgument::OPTIONAL, '',self::ALL)
            ->addArgument('axis_attribute_code', InputArgument::OPTIONAL, '',self::ALL)
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->inputFamilyCode = $input->getArgument('family_code');
        $this->inputAxisCode = $input->getArgument('axis_attribute_code');

        if (($this->inputFamilyCode !== self::ALL && $this->inputAxisCode === self::ALL)
            || ($this->inputFamilyCode === self::ALL && $this->inputAxisCode !== self::ALL)) {
            throw new \Exception("You have to specify a family code AND an axis code, or none at all.");
        }

        $stmt = $this->getStmt();

        $data = [];
        while ($row = $stmt->fetch()) {
            $values = json_decode($row['valuesData'], true);
            if (empty($values)) {
                $values = [];
            }
            $keys = array_keys($values);
            sort($keys);
            $keys = implode(',', $keys);

            if (!isset($data[$row['family_code']])) {
                $data[$row['family_code']] = [];
            }
            if (!isset($data[$row['family_code']][$row['axis_code']])) {
                $data[$row['family_code']][$row['axis_code']] = [];
            }

            $found = false;
            foreach ($data[$row['family_code']][$row['axis_code']] as &$item) {
                if ($item['keys'] == $keys) {
                    $item['group_codes'] .= ','.$row['group_codes'];
                    $found = true;
                }
            }
            if (!$found) {
                $data[$row['family_code']][$row['axis_code']][] =[
                    'keys' => $keys,
                    'group_codes' => $row['group_codes'],
                ];
            }
        }

        if ($this->inputFamilyCode === self::ALL) {
            $this->displayGeneralSummary($output, $data);
        } else {
            $this->displayPairSummary($output, $data);
        }
    }

    protected function getSql()
    {
        $isPrepared = $this->inputFamilyCode !== self::ALL;

        $sql = "select GROUP_CONCAT(DISTINCT g.code) as group_codes, f.code as family_code, a.code as axis_code, pcpt.valuesData 
       from pim_catalog_group g 
       left join pim_catalog_product_template pcpt on g.product_template_id = pcpt.id 
       join pim_catalog_group_attribute ga on g.id = ga.group_id 
       join pim_catalog_attribute a on a.id = ga.attribute_id";

        if ($isPrepared) {
            $sql .= " and a.code=?";
        }

        $sql .= " join pim_catalog_group_product gp on g.id = gp.group_id 
       join pim_catalog_product p on gp.product_id = p.id 
       join pim_catalog_family f on p.family_id = f.id";

        if ($isPrepared) {
            $sql .= " and f.code=?";
        }

        $sql .= "  where g.type_id=1 group by f.code, a.code, pcpt.valuesData;";

        return $sql;
    }

    protected function getSqlParams()
    {
        if ($this->inputFamilyCode !== self::ALL) {
            return [$this->inputAxisCode, $this->inputFamilyCode];
        }

        return null;
    }

    private function displayGeneralSummary(OutputInterface $output, array $data)
    {
        $errors = [];
        foreach (array_keys($data) as $familyCode) {
            foreach (array_keys($data[$familyCode]) as $axisCode) {
                if (count($data[$familyCode][$axisCode]) > 1) {
                    $errors[] = [
                        'family_code' => $familyCode,
                        'axis_code' => $axisCode,
                    ];
                    $output->writeln(sprintf(
                        "Unable to migrate the variations for the family <info>%s</info> and axis <info>%s</info>, because all their variation groups don't have the same attributes",
                        $familyCode,
                        $axisCode
                    ));
                }
            }
        }
        if (count($errors) > 0) {
            $output->writeln('<error>Unable to migrate the variations, because all their variation groups don\'t have the same attributes');
            $this->displayResultsInATable($output, [
                'headers' => ["Family code", "Axis attribute code"],
                'rows' => $errors,
            ]);
        }
    }

    private function displayPairSummary(OutputInterface $output, array $data)
    {
        $output->writeln(
            sprintf("<info>Analyse des attributs des groupes de variations de la famille %s sur l'axe %s</info>",
                $this->inputFamilyCode,
                $this->inputAxisCode
            ));
        $rows = [];
        foreach ($data[$this->inputFamilyCode][$this->inputAxisCode] as $item) {
            $rows[] = [
                $this->formatList($item['group_codes']),
                $this->formatList($item['keys']),
            ];
        }
        $this->displayResultsInATable($output, [
            'headers' => ["Variant groups", "Configured attributes"],
            'rows' => $rows,
        ], true);

        if (count($data[$this->inputFamilyCode][$this->inputAxisCode]) > 1) {
            $output->writeln("<error>The current structure of the variation groups cannot be migrated with Transporteo. You have to fix it before. You may rerun this command with the -v options to get more details on encountered problems.</error>");
        } else {
            $output->writeln("<info>The current structure of the variation groups can be migrated with Transporteo</info>");
        }
    }
}
