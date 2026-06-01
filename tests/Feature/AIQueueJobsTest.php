<?php

declare(strict_types=1);

use App\Jobs\AIAnalysisJob;
use App\Jobs\AIChatJob;
use App\Jobs\DocumentationJob;
use App\Jobs\HealthScoreJob;
use Illuminate\Support\Facades\Queue;

test('AIAnalysisJob dispatches with correct parameters', function () {
    Queue::fake();

    AIAnalysisJob::dispatch(
        connectionId: '1',
        connectionName: 'TestDB',
        provider: 'openai',
        apiKey: 'sk-test-key',
        systemPrompt: 'You are a database expert.',
    );

    Queue::assertPushed(AIAnalysisJob::class, function (AIAnalysisJob $job) {
        expect($job)->toBeInstanceOf(AIAnalysisJob::class);

        // Use reflection to check private properties
        $ref = new ReflectionClass($job);
        $connId = $ref->getProperty('connectionId');
        $connId->setAccessible(true);

        $connName = $ref->getProperty('connectionName');
        $connName->setAccessible(true);

        $provider = $ref->getProperty('provider');
        $provider->setAccessible(true);

        return $connId->getValue($job) === '1'
            && $connName->getValue($job) === 'TestDB'
            && $provider->getValue($job) === 'openai';
    });
});

test('AIAnalysisJob has correct timeout and retry configuration', function () {
    $job = new AIAnalysisJob(
        connectionId: '1',
        connectionName: 'Test',
        provider: 'openai',
        apiKey: '',
        systemPrompt: '',
    );

    expect($job->timeout)->toBe(300)
        ->and($job->tries)->toBe(1);
});

test('AIChatJob dispatches with correct parameters', function () {
    Queue::fake();

    AIChatJob::dispatch(
        connectionId: '2',
        connectionName: 'ChatDB',
        message: 'How can I optimize this query?',
        history: [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi, how can I help?'],
        ],
        provider: 'anthropic',
        apiKey: 'sk-ant-test',
        systemPrompt: 'You are a helpful assistant.',
    );

    Queue::assertPushed(AIChatJob::class, function (AIChatJob $job) {
        $ref = new ReflectionClass($job);

        $connId = $ref->getProperty('connectionId');
        $connId->setAccessible(true);

        $msg = $ref->getProperty('message');
        $msg->setAccessible(true);

        $provider = $ref->getProperty('provider');
        $provider->setAccessible(true);

        return $connId->getValue($job) === '2'
            && $msg->getValue($job) === 'How can I optimize this query?'
            && $provider->getValue($job) === 'anthropic';
    });
});

test('AIChatJob has correct timeout and retry configuration', function () {
    $job = new AIChatJob(
        connectionId: '1',
        connectionName: 'Test',
        message: 'hello',
        history: [],
        provider: 'openai',
        apiKey: '',
        systemPrompt: '',
    );

    expect($job->timeout)->toBe(300)
        ->and($job->tries)->toBe(1);
});

test('HealthScoreJob dispatches with correct parameters', function () {
    Queue::fake();

    HealthScoreJob::dispatch(connectionId: '3');

    Queue::assertPushed(HealthScoreJob::class, function (HealthScoreJob $job) {
        $ref = new ReflectionClass($job);

        $connId = $ref->getProperty('connectionId');
        $connId->setAccessible(true);

        return $connId->getValue($job) === '3';
    });
});

test('HealthScoreJob has correct timeout and retry configuration', function () {
    $job = new HealthScoreJob(connectionId: '5');

    expect($job->timeout)->toBe(180)
        ->and($job->tries)->toBe(1);
});

test('DocumentationJob dispatches with correct parameters', function () {
    Queue::fake();

    DocumentationJob::dispatch(
        connectionId: '4',
        provider: 'deepseek',
        apiKey: 'sk-ds-test',
        systemPrompt: '',
        format: 'html',
    );

    Queue::assertPushed(DocumentationJob::class, function (DocumentationJob $job) {
        $ref = new ReflectionClass($job);

        $connId = $ref->getProperty('connectionId');
        $connId->setAccessible(true);

        $provider = $ref->getProperty('provider');
        $provider->setAccessible(true);

        $format = $ref->getProperty('format');
        $format->setAccessible(true);

        return $connId->getValue($job) === '4'
            && $provider->getValue($job) === 'deepseek'
            && $format->getValue($job) === 'html';
    });
});

test('DocumentationJob has correct timeout and retry configuration', function () {
    $job = new DocumentationJob(
        connectionId: '1',
        provider: 'openai',
        apiKey: '',
        systemPrompt: '',
        format: 'markdown',
    );

    expect($job->timeout)->toBe(300)
        ->and($job->tries)->toBe(1);
});

test('all job classes implement ShouldQueue', function () {
    $jobs = [
        AIAnalysisJob::class,
        AIChatJob::class,
        HealthScoreJob::class,
        DocumentationJob::class,
    ];

    foreach ($jobs as $jobClass) {
        expect(new ReflectionClass($jobClass))
            ->implementsInterface(Illuminate\Contracts\Queue\ShouldQueue::class)->toBeTrue();
    }
});
