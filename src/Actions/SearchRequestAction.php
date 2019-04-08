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

use Mcustiel\Phiremock\Domain\MockConfig;
use Mcustiel\Phiremock\Server\Model\ExpectationStorage;
use Mcustiel\Phiremock\Server\Model\RequestStorage;
use Mcustiel\Phiremock\Server\Model\ScenarioStorage;
use Mcustiel\Phiremock\Server\Utils\RequestExpectationComparator;
use Mcustiel\Phiremock\Server\Utils\ResponseStrategyLocator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class SearchRequestAction implements ActionInterface
{
    /** @var \Mcustiel\Phiremock\Server\Model\ExpectationStorage */
    private $expectationsStorage;
    /** @var \Mcustiel\Phiremock\Server\Utils\RequestExpectationComparator */
    private $comparator;
    /** @var \Mcustiel\Phiremock\Server\Model\ScenarioStorage */
    private $scenariosStorage;
    /** @var \Psr\Log\LoggerInterface */
    private $logger;
    /** @var \Mcustiel\Phiremock\Server\Utils\ResponseStrategyLocator */
    private $responseStrategyFactory;
    /** @var \Mcustiel\Phiremock\Server\Model\RequestStorage */
    private $requestsStorage;

    /**
     * @param ExpectationStorage           $expectationsStorage
     * @param RequestExpectationComparator $comparator
     * @param LoggerInterface              $logger
     */
    public function __construct(
        ExpectationStorage $expectationsStorage,
        RequestExpectationComparator $comparator,
        ScenarioStorage $scenariosStorage,
        ResponseStrategyLocator $responseStrategyLocator,
        RequestStorage $requestsStorage,
        LoggerInterface $logger
    ) {
        $this->expectationsStorage = $expectationsStorage;
        $this->comparator = $comparator;
        $this->logger = $logger;
        $this->requestsStorage = $requestsStorage;
        $this->responseStrategyFactory = $responseStrategyLocator;
        $this->scenariosStorage = $scenariosStorage;
    }

    public function execute(ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->logger->debug('Searching matching expectation for request');
        $this->logger->info('Request received: ' . $this->getLoggableRequest($request));
        $this->requestsStorage->addRequest($request);
        $foundExpectation = $this->searchForMatchingExpectation($request);
        if (null === $foundExpectation) {
            return $response->withStatus(404, 'Not Found');
        }
        $this->processScenario($foundExpectation);

        $response = $this->responseStrategyFactory
            ->getStrategyForExpectation($foundExpectation)
            ->createResponse($foundExpectation, $response);

        $this->logger->debug('Responding: ' . $this->getLoggableResponse($response));

        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return null|\Mcustiel\Phiremock\Domain\MockConfig
     */
    private function searchForMatchingExpectation(ServerRequestInterface $request)
    {
        $lastFound = null;
        foreach ($this->expectationsStorage->listExpectations() as $expectation) {
            $lastFound = $this->getNextMatchingExpectation($lastFound, $request, $expectation);
        }

        return $lastFound;
    }

    /**
     * @param null|\Mcustiel\Phiremock\Domain\MockConfig $lastFound
     * @param ServerRequestInterface                     $request
     * @param MockConfig                                 $expectation
     *
     * @return \Mcustiel\Phiremock\Domain\MockConfig
     */
    private function getNextMatchingExpectation($lastFound, ServerRequestInterface $request, MockConfig $expectation)
    {
        if ($this->comparator->equals($request, $expectation)) {
            if (null === $lastFound || $expectation->getPriority() > $lastFound->getPriority()) {
                $lastFound = $expectation;
            }
        }

        return $lastFound;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return string
     */
    private function getLoggableRequest(ServerRequestInterface $request)
    {
        return $request->getMethod() . ': '
            . $request->getUri()->__toString() . ' || '
                . preg_replace('|\s+|', ' ', $request->getBody()->__toString());
    }
}