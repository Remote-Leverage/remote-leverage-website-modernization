<?php

declare(strict_types=1);

use App\Application\Http\Controllers\CostAlertSendController;
use App\Domains\Marketing\Actions\SendCostAlertAction;
use App\Domains\Marketing\Support\CostAlertSendLink;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/*
 * The "Send new alert" button on the cost alert card, asked for from Slack on 2026-09-24.
 *
 * It is a signed link rather than an interactive button — see CostAlertSendLink for why — so
 * everything that makes it safe lives on this side: the signature is the permission, the GET
 * posts nothing, and a double press posts one card.
 */

/**
 * The action, doubled. Records what it was asked and answers as told, so these tests are about
 * the button and not about the card, which MarketingCostAlertTest already covers.
 */
final class RecordingSendCostAlertAction extends SendCostAlertAction
{
    /** @var array<int, array{force: bool}> */
    public array $calls = [];

    public function __construct(private bool $answer = true, private ?Throwable $failure = null) {}

    public function execute(?CarbonImmutable $now = null, bool $force = false, ?callable $onProgress = null): bool
    {
        $this->calls[] = ['force' => $force];

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->answer;
    }
}

function recordingSendAction(bool $result = true, ?Throwable $throw = null): RecordingSendCostAlertAction
{
    return new RecordingSendCostAlertAction($result, $throw);
}

/**
 * The signed values out of a minted link, the way the page would post them back.
 *
 * @return array{expires: string, sig: string}
 */
function sendLinkParams(?int $now = null): array
{
    parse_str((string) parse_url(CostAlertSendLink::url($now), PHP_URL_QUERY), $query);

    return ['expires' => (string) $query['expires'], 'sig' => (string) $query['sig']];
}

function sendButtonPress(array $params): Request
{
    return Request::create('/api/marketing/cost-alert/send', 'POST', $params);
}

beforeEach(function () {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('c', 32))]);

    Cache::lock(CostAlertSendController::LOCK)->forceRelease();
});

describe('the signed link', function () {
    test('a link the card minted is accepted', function () {
        $url = CostAlertSendLink::url();

        expect($url)->toStartWith('https://remoteleverage.com/cost-alert/send/?expires=');

        $params = sendLinkParams();

        expect(CostAlertSendLink::check($params['expires'], $params['sig']))->toBeNull();
    });

    test('a changed signature is refused', function () {
        $params = sendLinkParams();
        $forged = substr($params['sig'], 0, -1).($params['sig'][-1] === 'a' ? 'b' : 'a');

        expect(CostAlertSendLink::check($params['expires'], $forged))->toBe(CostAlertSendLink::INVALID);
    });

    /*
     * The expiry is inside the signature, so it cannot be pushed out by editing the URL. A link
     * that could be is one that never expires.
     */
    test('a pushed-out expiry is refused rather than honoured', function () {
        $params = sendLinkParams();

        expect(CostAlertSendLink::check((string) ((int) $params['expires'] + 86400), $params['sig']))
            ->toBe(CostAlertSendLink::INVALID);
    });

    test('a genuine link past its week says expired, not invalid', function () {
        $mintedAt = time() - CostAlertSendLink::TTL_SECONDS - 60;
        $params = sendLinkParams($mintedAt);

        expect(CostAlertSendLink::check($params['expires'], $params['sig']))->toBe(CostAlertSendLink::EXPIRED);
    });

    test('the last card of a Friday still works on Monday morning', function () {
        $friday = CarbonImmutable::parse('2026-09-25 18:00:00', 'America/New_York');
        $monday = CarbonImmutable::parse('2026-09-28 09:00:00', 'America/New_York');
        $params = sendLinkParams($friday->getTimestamp());

        expect(CostAlertSendLink::check($params['expires'], $params['sig'], $monday->getTimestamp()))->toBeNull();
    });

    test('anything that is not a pair of strings is refused', function () {
        $params = sendLinkParams();

        expect(CostAlertSendLink::check(null, null))->toBe(CostAlertSendLink::INVALID)
            ->and(CostAlertSendLink::check([$params['expires']], $params['sig']))->toBe(CostAlertSendLink::INVALID)
            ->and(CostAlertSendLink::check($params['expires'], [$params['sig']]))->toBe(CostAlertSendLink::INVALID)
            ->and(CostAlertSendLink::check('1e12', $params['sig']))->toBe(CostAlertSendLink::INVALID);
    });

    /*
     * No key means no button, rather than a button signed with something guessable. A forgeable
     * link here posts to a channel people read.
     */
    test('an install with no key gets no button and accepts no link', function () {
        $params = sendLinkParams();
        config(['app.key' => '']);

        expect(CostAlertSendLink::url())->toBe('')
            ->and(CostAlertSendLink::check($params['expires'], $params['sig']))->toBe(CostAlertSendLink::INVALID);
    });
});

describe('pressing the button', function () {
    test('a good link sends, and sends the way the dashboard button does', function () {
        $action = recordingSendAction();

        $response = (new CostAlertSendController($action))->store(sendButtonPress(sendLinkParams()));

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true)['sent'])->toBeTrue()
            ->and($action->calls)->toBe([['force' => true]]);
    });

    test('a forged link sends nothing', function () {
        $action = recordingSendAction();
        $params = sendLinkParams();
        $params['sig'] = str_repeat('0', 32);

        $response = (new CostAlertSendController($action))->store(sendButtonPress($params));

        expect($response->getStatusCode())->toBe(403)
            ->and($response->getData(true)['sent'])->toBeFalse()
            ->and($action->calls)->toBeEmpty();
    });

    test('an expired link sends nothing and says so', function () {
        $action = recordingSendAction();
        $params = sendLinkParams(time() - CostAlertSendLink::TTL_SECONDS - 60);

        $response = (new CostAlertSendController($action))->store(sendButtonPress($params));

        expect($response->getStatusCode())->toBe(410)
            ->and($response->getData(true)['message'])->toContain('expired')
            ->and($action->calls)->toBeEmpty();
    });

    test('a double press posts one card', function () {
        $action = recordingSendAction();
        $controller = new CostAlertSendController($action);

        $first = $controller->store(sendButtonPress(sendLinkParams()));
        $second = $controller->store(sendButtonPress(sendLinkParams()));

        expect($first->getStatusCode())->toBe(200)
            ->and($second->getStatusCode())->toBe(429)
            ->and($second->getData(true)['message'])->toContain('Check the channel')
            ->and($action->calls)->toHaveCount(1);
    });

    /*
     * The cooldown is for presses that succeeded. Holding it after a failure would refuse the
     * retry of somebody who was just told nothing was sent.
     */
    test('a send that posted nothing can be retried straight away', function () {
        $action = recordingSendAction(result: false);
        $controller = new CostAlertSendController($action);

        $first = $controller->store(sendButtonPress(sendLinkParams()));
        $second = $controller->store(sendButtonPress(sendLinkParams()));

        expect($first->getData(true)['sent'])->toBeFalse()
            ->and($second->getStatusCode())->toBe(200)
            ->and($action->calls)->toHaveCount(2);
    });

    test('a send that threw can be retried, and the exception text stays in the log', function () {
        $action = recordingSendAction(throw: new RuntimeException('BigQuery said no: project rl-secret-123'));
        $controller = new CostAlertSendController($action);

        $first = $controller->store(sendButtonPress(sendLinkParams()));
        $second = $controller->store(sendButtonPress(sendLinkParams()));

        expect($first->getStatusCode())->toBe(500)
            ->and($first->getData(true)['message'])->not->toContain('rl-secret-123')
            ->and($second->getStatusCode())->toBe(500)
            ->and($action->calls)->toHaveCount(2);
    });

    test('no answer is cached, indexed or passed on as a referrer', function () {
        $response = (new CostAlertSendController(recordingSendAction()))->store(sendButtonPress(sendLinkParams()));

        expect($response->headers->get('Cache-Control'))->toContain('no-store')
            ->and($response->headers->get('X-Robots-Tag'))->toContain('noindex')
            ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer');
    });
});

/*
 * The page the link opens. The suite has no view factory, so one that hands back its data is
 * bound for these tests: what is under test is what the controller decides, not the markup.
 */
describe('opening the link', function () {
    beforeEach(function () {
        app()->instance(ViewFactory::class, new class
        {
            // Acorn's view() asks before it makes.
            public function exists(string $view): bool
            {
                return true;
            }

            public function make(string $view, array $data = [], array $mergeData = []): object
            {
                return new class($view, $data)
                {
                    public function __construct(private string $view, private array $data) {}

                    public function render(): string
                    {
                        return (string) json_encode(['view' => $this->view] + $this->data);
                    }
                };
            }
        });
    });

    afterEach(function () {
        app()->offsetUnset(ViewFactory::class);
    });

    /*
     * The whole reason for the page. A GET that posted would fire on anything that fetched the
     * URL, and the send is the POST the page makes once it is open in a browser.
     */
    test('opening a good link posts nothing by itself', function () {
        $action = recordingSendAction();
        $params = sendLinkParams();

        $response = (new CostAlertSendController($action))->show(Request::create('/cost-alert/send', 'GET', $params));
        $page = json_decode((string) $response->getContent(), true);

        expect($response->getStatusCode())->toBe(200)
            ->and($action->calls)->toBeEmpty()
            ->and($page['view'])->toBe('pages.cost-alert-send')
            ->and($page['problem'])->toBeNull()
            ->and($page['expires'])->toBe($params['expires'])
            ->and($page['sig'])->toBe($params['sig'])
            ->and($page['endpoint'])->toBe('https://remoteleverage.com/api/marketing/cost-alert/send');
    });

    test('a bad link opens a page that says so and echoes nothing back', function () {
        $params = sendLinkParams();
        $params['sig'] = 'nope';

        $response = (new CostAlertSendController(recordingSendAction()))->show(Request::create('/cost-alert/send', 'GET', $params));
        $page = json_decode((string) $response->getContent(), true);

        expect($response->getStatusCode())->toBe(403)
            ->and($page['problem'])->toBe(CostAlertSendLink::INVALID)
            ->and($page['message'])->toContain('not one the site recognises')
            ->and($page['sig'])->toBe('')
            ->and($page['expires'])->toBe('');
    });

    test('an expired link opens a page that says expired', function () {
        $params = sendLinkParams(time() - CostAlertSendLink::TTL_SECONDS - 60);

        $response = (new CostAlertSendController(recordingSendAction()))->show(Request::create('/cost-alert/send', 'GET', $params));

        expect($response->getStatusCode())->toBe(410)
            ->and(json_decode((string) $response->getContent(), true)['message'])->toContain('expired');
    });
});

describe('the page itself', function () {
    $source = fn (): string => (string) file_get_contents(__DIR__.'/../../resources/views/pages/cost-alert-send.blade.php');

    /*
     * The site layout would load every tracking pixel to show two lines of status, and record a
     * pageview for a page no visitor reaches.
     */
    test('does not load the site layout', function () use ($source) {
        expect($source())->not->toContain('@extends');
    });

    test('sends by POSTing the signed values back, and only when they verified', function () use ($source) {
        $page = $source();

        expect($page)->toContain("method: 'POST'")
            ->and($page)->toContain("root.dataset.ready !== '1'")
            ->and($page)->toContain('name="robots" content="noindex, nofollow"');
    });
});
