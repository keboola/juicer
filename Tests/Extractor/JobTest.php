<?php

use Keboola\Juicer\Config\JobConfig,
    Keboola\Juicer\Client\RestClient,
    Keboola\Juicer\Parser\Json,
    Keboola\Juicer\Pagination\ResponseUrlScroller,
    Keboola\Juicer\Extractor\Job;

use Keboola\Json\Parser;

use GuzzleHttp\Client,
    GuzzleHttp\Message\Response,
    GuzzleHttp\Stream\Stream,
    GuzzleHttp\Subscriber\Mock,
    GuzzleHttp\Subscriber\History;
// mock guzzle, do 2 pages with run, check output
// recursivejobtest too w/ scroller reset
class JobTest extends ExtractorTestCase
{
    public function testRun()
    {
        $config = JobConfig::create([
            'endpoint' => 'api/ep',
            'params' => [
                'first' => 'one',
                'second' => 2
            ]
        ]);

        $first = '{
            "data": [
                {"field": "one"},
                {"field": "two"}
            ],
            "next_page": "api/ep/2"
        }';

        $second = '{
            "data": [
                {"field": "three"},
                {"field": "four"}
            ],
            "next_page": ""
        }';

        $mock = new Mock([
            new Response(200, [], Stream::factory($first)),
            new Response(200, [], Stream::factory($second))
        ]);

        // FIXME not used
        $history = new History();

        $client = RestClient::create();
        $client->getClient()->getEmitter()->attach($mock);
        $client->getClient()->getEmitter()->attach($history);

        $parser = new Json(Parser::create($this->getLogger('job', true)));

        $job = new Job($config, $client, $parser);
        $job->setScroller(ResponseUrlScroller::create([]));

        $job->run();

        $this->assertEquals(
            '"field"
"one"
"two"
"three"
"four"
',
            file_get_contents($parser->getResults()['api_ep'])
        );
    }

    /**
     * @deprecated
     */
    public function testFindDataInResponse()
    {
        $cfg = JobConfig::create([
            'endpoint' => 'a',
            'dataField' => 'results'
        ]);
        $job = new Job(
            $cfg,
            RestClient::create(),
            new Json(Parser::create($this->getLogger('job', true)))
        );

        $response = (object) [
            'results' => [
                (object) ['id' => 1],
                (object) ['id' => 2]
            ],
            'otherArray' => ['a','b']
        ];

        $data = $this->callMethod($job, 'findDataInResponse', [$response, $cfg->getConfig()]);
        $this->assertEquals($data, $response->{$cfg->getConfig()['dataField']});
    }

    public function testFindDataInResponseNested()
    {
        $cfg = JobConfig::create([
            'endpoint' => 'a',
            'dataField' => 'data.results'
        ]);
        $job = new Job(
            $cfg,
            RestClient::create(),
            new Json(Parser::create($this->getLogger('job', true)))
        );

        $response = (object) [
            'data' => (object) [
                'results' => [
                    (object) ['id' => 1],
                    (object) ['id' => 2]
                ]
            ],
            'otherArray' => ['a','b']
        ];

        $data = $this->callMethod($job, 'findDataInResponse', [$response, $cfg->getConfig()]);
        $this->assertEquals($data, $response->data->results);
    }

    /**
     * @expectedException \Keboola\Juicer\Exception\UserException
     * @expectedExceptionMessage More than one array found in response! Use 'dataField' parameter to specify a key to the data array. (endpoint: a, arrays in response root: results, otherArray)
     */
    public function testFindDataInResponseException()
    {
        $cfg = JobConfig::create([
            'endpoint' => 'a'
        ]);
        $job = new Job(
            $cfg,
            RestClient::create(),
            new Json(Parser::create($this->getLogger('job', true)))
        );

        $response = (object) [
            'results' => [
                (object) ['id' => 1],
                (object) ['id' => 2]
            ],
            'otherArray' => ['a','b']
        ];

        $data = $this->callMethod($job, 'findDataInResponse', [$response, $cfg->getConfig()]);
    }
}
