<?php

namespace App\Mob407\V3;

use App\Mob407\V3\Helpers\HasSources;
use App\Mob407\V3\Models\Driver;
use App\Mob407\V3\Reports\Group1Report;
use App\Mob407\V3\Reports\Group2Report;
use App\Mob407\V3\Reports\Group3Report;
use App\Mob407\V3\Reports\GroupWithRefundsReport;
use Symfony\Component\Console\Output\Output;

class Verifier
{
    use HasSources;

    protected function __construct(
        private readonly Output $logger,
        private readonly Output $output,
    ) {}

    public static function init(Output $logger, Output $output): static
    {
        return new static($logger, $output);
    }

    public function verify(): void
    {
        $ignoreUncovered = [
            25293055,30085595,32078665,32270745,32271225,32457425,32464575,32629215,32881835,32900075,33065155,
            33066105,34574465,34575945,34576985,34577695,34971715,35131575,35223445,35358815,35364125,35365175,
            35783975,35818075,35826655,35831225,35952605,36088115,36219535,36783695,36884635,37325135,
            // Added after manual check
            23538975,25293055,26322795,32078665,32270745,32271225,32457425,32464575,32629215,32881835,32900075,33065155,
            26322795,36215245,21371095,25921315,28998895,32278035,32623995,33841645,34224645,35129845,35365605,36267455,
            37168865,37678885,
        ];
        $allAffected = $this->getAffectedDrivers();
        $group1 = $this->getGroup1Drivers();
        $group2 = $this->getGroup2Drivers();
        $group3 = $this->getGroup3Drivers();
        $groupWithRefunds = $this->getGroupWithRefundsDrivers();
        $allCovered = array_merge($group1, $group2, $group3, $groupWithRefunds);
        $allCoveredUnique = array_unique($allCovered);

        $countCovered = count($allCovered);
        $countCoveredUnique = count($allCoveredUnique);

        $uncovered = array_diff($allAffected, $allCoveredUnique);
        $uncovered = array_filter(
            $uncovered,
            fn ($id) => !in_array($id, $ignoreUncovered),
        );

        if (count($uncovered) > 0) {
            $this->output->writeln(sprintf(' - Check %d uncovered case(s)', count($uncovered)));
            $this->output->writeln(sprintf(' - %s', implode(', ', $uncovered)));
        }


        if (count(array_diff($allCovered, $allCoveredUnique))) {
            $this->output->writeln(sprintf(' - Check %d duplicate(s) in covered cases', $countCovered - $countCoveredUnique));
            $this->output->writeln(sprintf(' - %s', implode(', ', array_diff($allCovered, $allCoveredUnique))));
        }
    }

    private function getAffectedDrivers(): array
    {
        return Driver::query()
            ->select('id')
            ->where('is_affected', 1)
            ->get()
            ->pluck('id')
            ->toArray();
    }

    private function getGroup1Drivers(): array
    {
        return $this->readCommaSeparatedListFromFile(Group1Report::REPORT_NAME);
    }

    private function getGroup2Drivers(): array
    {
        return $this->readCommaSeparatedListFromFile(Group2Report::REPORT_NAME);
    }

    private function getGroup3Drivers(): array
    {
        return $this->readCommaSeparatedListFromFile(Group3Report::REPORT_NAME);
    }

    private function getGroupWithRefundsDrivers(): array
    {
        return $this->readCommaSeparatedListFromFile(GroupWithRefundsReport::REPORT_NAME);
    }

    private function readCommaSeparatedListFromFile(string $sourceKey): array
    {
        try {
        $fileName = $this->getSourceFile($sourceKey);
        } catch (\InvalidArgumentException $e) {
            $this->log('File cannot be open: ' . $e->getMessage(), 'warning');
            return [];
        }

        $commaSeparatedDriverIds = file_get_contents($fileName);
        $driverIds = explode(',', $commaSeparatedDriverIds);
        $driverIds = array_map('intval', $driverIds);

        return $driverIds;
    }

    private function log(string $message, string $level = 'info'): void
    {
        $this->logger->writeln(sprintf('[%s] %s %s', date('Y-m-d H:i:s'), strtoupper($level), $message));
    }
}
