<?php declare(strict_types=1);

namespace JonasPardon\LaravelEventVisualizer\Tests\Unit\CodeParser;

use JonasPardon\LaravelEventVisualizer\Services\CodeParser\CodeParser;
use JonasPardon\LaravelEventVisualizer\Services\CodeParser\ValueObjects\ResolvedCall;
use JonasPardon\LaravelEventVisualizer\Tests\TestCase;

final class MethodCallsParsingTest extends TestCase
{
    /**
     * @test
     * @dataProvider providesCodeSamples
     */
    public function it_can_find_method_calls(
        string $code,
        string $subjectClass,
        string $methodName,
        array $expectedMethodCalls,
    ): void {
        // dump("Looking for {$subjectClass}->{$methodName}", $code);
        $codeParser = new CodeParser($code);

        $methodCalls = $codeParser->getMethodCalls(
            subjectClass: $subjectClass,
            methodName: $methodName,
        );

        $this->assertCount(count($expectedMethodCalls), $methodCalls);
        $this->assertEquals($expectedMethodCalls, $methodCalls);
    }

    public function providesCodeSamples(): array
    {
        return [
            'dispatch call on Event contract without import' => [
                <<<'CODE'
                <?php declare(strict_types=1);
                
                final class ClassName
                {
                    public function __construct(private \Illuminate\Contracts\Events\Dispatcher $dispatcher)
                    {
                    }
                
                    public function classMethod(): void
                    {
                        $dispatcher->dispatch(new \App\Events\SomeEvent());
                    }
                }
                CODE,
                'Illuminate\Contracts\Events\Dispatcher',
                'dispatch',
                [
                    new ResolvedCall(
                        dispatcherClass: 'Illuminate\Contracts\Events\Dispatcher',
                        dispatchedClass: 'App\Events\SomeEvent',
                        method: 'dispatch',
                    ),
                ],
            ],
            'dispatch call on Event contract with import' => [
                <<<'CODE'
                <?php declare(strict_types=1);
                
                use \Illuminate\Contracts\Events\Dispatcher;
                
                final class ClassName
                {
                    public function __construct(private Dispatcher $dispatcher)
                    {
                    }
                
                    public function classMethod(): void
                    {
                        $dispatcher->dispatch(new \App\Events\SomeEvent());
                    }
                }
                CODE,
                'Illuminate\Contracts\Events\Dispatcher',
                'dispatch',
                [
                    new ResolvedCall(
                        dispatcherClass: 'Illuminate\Contracts\Events\Dispatcher',
                        dispatchedClass: 'App\Events\SomeEvent',
                        method: 'dispatch',
                    ),
                ],
            ],
            'subscriber with injected job and event dispatchers' => [
                <<<'CODE'
                <?php declare(strict_types=1);
                
                namespace App\Subscribers;
                
                use App\Events\Event1;
                use App\Events\Event2;
                use App\Events\Event3;
                use App\Jobs\Job1;
                use App\Jobs\Job2;
                use Illuminate\Contracts\Bus\Dispatcher as JobDispatcher;
                use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
                use Illuminate\Contracts\Queue\ShouldQueue;
                use Illuminate\Queue\InteractsWithQueue;
                
                final class ClassName implements ShouldQueue
                {
                    use InteractsWithQueue;
                
                    public function __construct(
                        private JobDispatcher $jobDispatcher
                    ) {
                    }
                
                    public function handle(Event1|Event2|Event3 $event): void
                    {
                        $var = $event->getVar();
                
                        $this->jobDispatcher->dispatchNow(new Job1($var));
                        $this->jobDispatcher->dispatchNow(new Job2($var));
                    }
                
                    public function subscribe(EventDispatcher $dispatcher): void
                    {
                        $dispatcher->listen(Event1::class, self::class . '@handle');
                        $dispatcher->listen(Event2::class, self::class . '@handle');
                        $dispatcher->listen(Event3::class, self::class . '@handle');
                    }
                }
                CODE,
                'Illuminate\Contracts\Bus\Dispatcher',
                'dispatchNow',
                [
                    new ResolvedCall(
                        dispatcherClass: 'Illuminate\Contracts\Bus\Dispatcher',
                        dispatchedClass: 'App\Jobs\Job1',
                        method: 'dispatchNow',
                    ),
                    new ResolvedCall(
                        dispatcherClass: 'Illuminate\Contracts\Bus\Dispatcher',
                        dispatchedClass: 'App\Jobs\Job2',
                        method: 'dispatchNow',
                    ),
                ],
            ],
            'listener with injected event dispatcher' => [
                <<<'CODE'
                <?php declare(strict_types=1);
                
                namespace App\Listeners;
                
                use App\Events\SomeEvent;
                use App\Models\SomeModel;
                use App\Domains\Documents\Esigning\Models\RelatedModel;
                use App\Factories\SomeFactory;
                use App\Factories\SomeOtherFactory;
                use App\Events\EventThatIsDispatched;
                use App\Mails\SomeMail;
                use Illuminate\Contracts\Events\Dispatcher;
                use Illuminate\Support\Facades\Log;
                use Illuminate\Support\Facades\Mail;
                use Psr\Log\LoggerInterface;
                
                final class SomeListener
                {
                    private LoggerInterface $logger;
                
                    public function __construct(
                        private SomeFactory $someFactory,
                        private SomeOtherFactory $someOtherFactory,
                        private Dispatcher $eventDispatcher,
                    ) {
                        $this->logger = Log::channel('channel');
                    }
                
                    public function handle(SomeEvent $event)
                    {
                        $var = $event->getVar();
                
                        $this->logger->info('Log some information', [
                            'varId' => $var->id,
                            'varOtherId' => $var->other_id,
                        ]);
                
                        $var->doSomething();
                
                        $var->loadMissing('relation1.relation2', 'relation3.relation4');
                
                        $someClient = $this->someFactory->forVar($var);
                
                        $var->relatedModels->each(function (RelatedModel $relatedModel) use ($someClient) {
                            $downloadedThing = $someClient->downloadThing($relatedModel);
                            $payload = $relatedModel->relation()->withTrashed()->first();
                
                            $filePath = $this->someOtherFactory
                                ->forCompany($var->company)
                                ->uploadThing(
                                    $payload,
                                    $downloadedThing,
                                );
                
                            $relatedModel->doSomethingWithPath($filePath);
                
                            $this->eventDispatcher->dispatch(new EventThatIsDispatched($payload));
                        });
                
                        $var->otherRelatedModel->each(function (SomeModel $someModel) {
                            Mail::to($someModel->email)
                                ->queue(new SomeMail($someModel));
                        });
                    }
                }
                CODE,
                'Illuminate\Contracts\Events\Dispatcher',
                'dispatch',
                [
                    new ResolvedCall(
                        dispatcherClass: 'Illuminate\Contracts\Events\Dispatcher',
                        dispatchedClass: 'App\Events\EventThatIsDispatched',
                        method: 'dispatch',
                    ),
                ],
            ],
        ];
    }
}
