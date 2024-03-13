<?php

declare(strict_types=1);

/***
 *
 * This file is part of the "ai_suite" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *
 ***/

namespace AutoDudes\AiSuite\Service;

use AutoDudes\AiSuite\Domain\Model\Dto\ServerAnswer\ClientAnswer;
use AutoDudes\AiSuite\Domain\Model\Dto\ServerRequest\ServerRequest;
use AutoDudes\AiSuite\Exception\AiSuiteServerException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\SingletonInterface;

class SendRequestService implements SingletonInterface
{
    protected RequestFactory $requestFactory;
    protected array $extConf;
    protected LoggerInterface $logger;

    public function __construct(
        RequestFactory $requestFactory,
        array $extConf,
        LoggerInterface $logger
    ) {
        $this->requestFactory = $requestFactory;
        $this->extConf = $extConf;
        $this->logger = $logger;
    }

    public function sendRequest(ServerRequest $serverRequest): ClientAnswer
    {
        try {
            $request = $this->requestFactory->request(
                $serverRequest->getEndpoint(),
                'POST',
                $serverRequest->getDataForRequest()
            );
            $requestContent = json_decode($request->getBody()->getContents(), true);
            if($requestContent === null) {
                throw new AiSuiteServerException('Could not fetch a valid response from request', 500);
            }
            return new ClientAnswer($requestContent, $requestContent['type']);
        }
        catch (ClientException|ServerException $exception) {
            $this->logger->error($exception->getMessage());
            return $this->buildErrorAnswer('AI Suite server endpoint is currently not available.');
        }
        catch (AiSuiteServerException $exception) {
            $this->logger->error($exception->getMessage());
            return $this->buildErrorAnswer($exception->getMessage());
        }
        catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            return $this->buildErrorAnswer('Unexpected AI Suite server endpoint error');
        }
    }

    private function buildErrorAnswer(string $message): ClientAnswer
    {
        return new ClientAnswer(
            [
                'body' => [
                    'message' => $message,
                ],
                'type' => 'Error',
            ],
            'Error'
        );
    }
}
