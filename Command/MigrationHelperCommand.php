<?php


namespace Kiboko\AkeneoMigrationHelperBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Output\OutputInterface;

abstract class MigrationHelperCommand extends ContainerAwareCommand
{
    abstract protected function getSql();

    protected function getSqlParams() {
        return null;
    }

    protected function getStmt()
    {
        $doctrine = $this->getContainer()->get('doctrine');
        $conn = $doctrine->getConnection();
        $stmt = $conn->prepare($this->getSql());
        if ($params = $this->getSqlParams()) {
            $k = 0;
            foreach ($params as $parameter) {
                $stmt->bindValue(++$k, $parameter);
            }
        }
        $stmt->execute();

        return $stmt;
    }

    protected function formatList($groupCodes)
    {
        return implode("\n", explode(',', $groupCodes));
    }

    protected function displayResultsInATable(OutputInterface $output, array $options, $separator=false)
    {
        if (empty($options['rows'])) {
            throw new \Exception("No data to display in the table.");
        }

        $table = new Table($output);
        if (isset($options['headers'])) {
            $table->setHeaders($options['headers']);
        }
        foreach ($options['rows'] as $i => $row) {
            $table->addRow($row);
            if ($separator && $i < count($options['rows']) - 1) {
                $table->addRow(new TableSeparator());
            }
        }
        $table->render();
    }
}
