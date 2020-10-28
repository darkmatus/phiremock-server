<?php
/**
 * This file is part of Phiremock.
 *
 * Phiremock is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Phiremock is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Phiremock.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Mcustiel\Phiremock\Server\Actions;

use Mcustiel\Phiremock\Server\Model\ExpectationStorage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class ReloadPreconfiguredExpectationsAction implements ActionInterface
{
    /**
     * @var ExpectationStorage
     */
    private $expectationStorage;
    /**
     * @var ExpectationStorage
     */
    private $expectationBackup;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ExpectationStorage $expectationStorage,
        ExpectationStorage $expectationBackup,
        LoggerInterface $logger
    ) {
        $this->expectationStorage = $expectationStorage;
        $this->expectationBackup = $expectationBackup;
        $this->logger = $logger;
    }

    public function execute(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $this->expectationStorage->clearExpectations();
        foreach ($this->expectationBackup->listExpectations() as $expectation) {
            $this->expectationStorage->addExpectation($expectation);
        }
        $this->logger->debug('Pre-defined expectations are restored, scenarios and requests history are cleared.');

        return $response->withStatus(200);
    }
}
