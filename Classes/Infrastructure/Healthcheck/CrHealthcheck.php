<?php

declare(strict_types=1);

namespace Neos\Neos\Setup\Infrastructure\Healthcheck;

use Neos\ContentRepository\Core\Infrastructure\DbalClientInterface;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckEnvironment;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;

class CrHealthcheck implements HealthcheckInterface
{
    public function __construct(
        private DbalClientInterface $dbalClient,
        private ConfigurationManager $configurationManager
    ) {
    }

    public function getTitle(): string
    {
        return 'Neos ContentRepository';
    }

    public function execute(HealthcheckEnvironment $environment): Health
    {
        // TODO: Implement execute() method.

        $crIdentifiers = array_keys(
            $this->configurationManager
                ->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Neos.ContentRepositoryRegistry.contentRepositories') ?? []
        );

        if (count($crIdentifiers) === 0) {
            return new Health(
                'No content repository is configured.',
                Status::ERROR(),
            );
        }

        $connection = $this->dbalClient->getConnection();
        $schemaManager = $connection->getSchemaManager();

        $existingTableNames = $schemaManager->listTableNames();

        foreach ($crIdentifiers as $crIdentifier) {
            $eventTableName = sprintf('cr_%s_events', $crIdentifier);

            if (!in_array($eventTableName, $existingTableNames, true)) {
                return new Health(
                    sprintf('Content repository "%s" was not setup. Please run <code>{{flowCommand}} cr:setup</code>', $crIdentifier),
                    Status::ERROR(),
                );
            }
        }

        // TODO check if `cr:setup` needs to be rerun, to "migrate" projections?

        if (count($crIdentifiers) === 1) {
            return new Health(
                sprintf('Content repository %sis setup.', $environment->isSafeToLeakTechnicalDetails() ? sprintf('"%s" ', $crIdentifiers[0]) : ''),
                Status::OK(),
            );
        }

        $additionalNote = sprintf('(%s) ', join(', ', $crIdentifiers));
        return new Health(
            sprintf('All content repositories %sare setup.', $environment->isSafeToLeakTechnicalDetails() ? $additionalNote : ''),
            Status::OK(),
        );
    }
}
