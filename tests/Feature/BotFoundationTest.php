<?php

use App\Actions\CreateDefaultBotSettings;
use App\Enums\BotTone;
use App\Enums\PrechatFieldType;
use App\Models\Bot;
use App\Models\BotPrechatField;
use App\Models\BotSetting;
use App\Models\BotStarterQuestion;
use App\Models\User;
use App\Services\BotConfigurationService;
use App\Services\BotDefaults;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

test('bots belong to their subscriber owner and owner scopes exclude other tenants', function () {
    $owner = User::factory()->subscriber()->create();
    $otherOwner = User::factory()->subscriber()->create();
    $ownedBot = Bot::factory()->for($owner)->create();
    $otherBot = Bot::factory()->for($otherOwner)->create();

    expect($ownedBot->user->is($owner))->toBeTrue()
        ->and($owner->bots()->sole()->is($ownedBot))->toBeTrue()
        ->and(Bot::query()->ownedBy($owner)->pluck('id')->all())->toBe([$ownedBot->id])
        ->and(Bot::query()->ownedBy($owner)->whereKey($otherBot)->exists())->toBeFalse();
});

test('bot slugs are unique per owner while separate owners may reuse them', function () {
    $owner = User::factory()->subscriber()->create();
    $otherOwner = User::factory()->subscriber()->create();

    Bot::factory()->for($owner)->create(['slug' => 'support-assistant']);
    Bot::factory()->for($otherOwner)->create(['slug' => 'support-assistant']);

    expect(fn () => Bot::factory()->for($owner)->create(['slug' => 'support-assistant']))
        ->toThrow(QueryException::class);

    $this->assertDatabaseCount('bots', 2);
});

test('bot public identifiers are globally unique and used for route binding', function () {
    $publicId = '01K5B0TF9B7C8D9E0F1G2H3J4K';
    $bot = Bot::factory()->create(['public_id' => $publicId]);

    expect($bot->getRouteKeyName())->toBe('public_id')
        ->and($bot->getRouteKey())->toBe($publicId)
        ->and(fn () => Bot::factory()->create(['public_id' => $publicId]))
        ->toThrow(QueryException::class);
});

test('default bot settings are valid idempotent and protected by a unique constraint', function () {
    $bot = Bot::factory()->create();
    $action = app(CreateDefaultBotSettings::class);

    $first = $action->handle($bot);
    $second = $action->handle($bot);

    expect($second->is($first))->toBeTrue()
        ->and($bot->fresh()->setting->is($first))->toBeTrue()
        ->and($first->tone)->toBe(BotTone::FRIENDLY)
        ->and($first->model_override)->toBeNull();

    $this->assertDatabaseCount('bot_settings', 1);

    expect(fn () => BotSetting::factory()->for($bot)->create())
        ->toThrow(QueryException::class);
});

test('bot defaults centralize display name and AI behavior settings', function () {
    $defaults = app(BotDefaults::class);
    $settings = $defaults->settings();

    expect($defaults->displayName('  Returns Assistant  ', null))->toBe('Returns Assistant')
        ->and($defaults->displayName('Internal Name', '  Customer Help  '))->toBe('Customer Help')
        ->and($settings['answer_only_from_knowledge_base'])->toBeTrue()
        ->and($settings['offer_human_handoff'])->toBeTrue()
        ->and($settings['model_override'])->toBeNull();
});

test('starter questions are replaced in deterministic order and limited to six', function () {
    $bot = Bot::factory()->create();
    $service = app(BotConfigurationService::class);

    $questions = $service->replaceStarterQuestions($bot, [
        '  How do returns work?  ',
        'Where is my order?',
        'Can I contact support?',
    ]);

    expect($questions->pluck('question')->all())->toBe([
        'How do returns work?',
        'Where is my order?',
        'Can I contact support?',
    ])->and($questions->pluck('position')->all())->toBe([1, 2, 3]);

    expect(fn () => $service->replaceStarterQuestions($bot, [
        'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven',
    ]))->toThrow(ValidationException::class);

    expect(fn () => BotStarterQuestion::factory()->for($bot)->create(['position' => 1]))
        ->toThrow(QueryException::class);
});

test('prechat fields use controlled keys types ordering and array casts', function () {
    $bot = Bot::factory()->create();
    $fields = app(BotConfigurationService::class)->replacePrechatFields($bot, [
        [
            'standard_key' => 'email',
            'label' => ' Email address ',
            'type' => 'text',
            'placeholder' => ' you@example.com ',
            'is_required' => true,
            'options' => null,
        ],
        [
            'standard_key' => null,
            'label' => 'Department',
            'type' => 'select',
            'placeholder' => null,
            'is_required' => false,
            'options' => [' Sales ', 'Support'],
        ],
    ]);

    expect($fields->pluck('position')->all())->toBe([1, 2])
        ->and($fields[0]->key)->toBe('email')
        ->and($fields[0]->type)->toBe(PrechatFieldType::EMAIL)
        ->and($fields[0]->is_required)->toBeTrue()
        ->and($fields[1]->key)->toBe('custom_department')
        ->and($fields[1]->type)->toBe(PrechatFieldType::SELECT)
        ->and($fields[1]->options)->toBe(['Sales', 'Support']);
});

test('prechat fields are limited to six and database uniqueness protects keys and positions', function () {
    $bot = Bot::factory()->create();
    $service = app(BotConfigurationService::class);
    $field = fn (int $number): array => [
        'standard_key' => null,
        'label' => 'Field '.$number,
        'type' => 'text',
        'placeholder' => null,
        'is_required' => false,
        'options' => null,
    ];

    expect(fn () => $service->replacePrechatFields($bot, array_map($field, range(1, 7))))
        ->toThrow(ValidationException::class);

    BotPrechatField::factory()->for($bot)->create(['key' => 'custom_topic', 'position' => 1]);

    expect(fn () => BotPrechatField::factory()->for($bot)->create([
        'key' => 'custom_topic',
        'position' => 2,
    ]))->toThrow(QueryException::class)
        ->and(fn () => BotPrechatField::factory()->for($bot)->create([
            'key' => 'custom_other',
            'position' => 1,
        ]))->toThrow(QueryException::class);
});

test('select prechat fields require options', function () {
    $bot = Bot::factory()->create();

    expect(fn () => app(BotConfigurationService::class)->replacePrechatFields($bot, [[
        'standard_key' => null,
        'label' => 'Department',
        'type' => 'select',
        'placeholder' => null,
        'is_required' => false,
        'options' => [],
    ]]))->toThrow(ValidationException::class);
});

test('settings validation accepts boundary values and rejects unsafe AI tuning', function () {
    config()->set('neuraldesk.ai.allowed_chat_models', ['approved-model']);
    $service = app(BotConfigurationService::class);
    $valid = app(BotDefaults::class)->settings();
    $valid['model_override'] = 'approved-model';
    $valid['temperature'] = '2.00';
    $valid['kb_confidence'] = '1.000';
    $valid['max_output_tokens'] = 8192;

    expect($service->validateSettings($valid))->toMatchArray($valid);

    foreach ([
        ['temperature', '-0.01'],
        ['temperature', '2.01'],
        ['kb_confidence', '-0.001'],
        ['kb_confidence', '1.001'],
        ['max_output_tokens', 0],
        ['max_output_tokens', 8193],
        ['model_override', 'unapproved-model'],
    ] as [$field, $value]) {
        $invalid = array_replace($valid, [$field => $value]);

        expect(fn () => $service->validateSettings($invalid))
            ->toThrow(ValidationException::class);
    }
});

test('bot setting and field casts expose controlled application values', function () {
    $bot = Bot::factory()->inactive()->create();
    $setting = BotSetting::factory()->create([
        'prechat_enabled' => 1,
        'offer_human_handoff' => 0,
        'temperature' => '0.45',
        'kb_confidence' => '0.725',
    ]);
    $field = BotPrechatField::factory()->create([
        'type' => PrechatFieldType::SELECT,
        'is_required' => 1,
        'options' => ['One', 'Two'],
    ]);

    expect($bot->is_active)->toBeFalse()
        ->and($setting->prechat_enabled)->toBeTrue()
        ->and($setting->offer_human_handoff)->toBeFalse()
        ->and($setting->temperature)->toBe('0.45')
        ->and($setting->kb_confidence)->toBe('0.725')
        ->and($field->type)->toBe(PrechatFieldType::SELECT)
        ->and($field->is_required)->toBeTrue()
        ->and($field->options)->toBe(['One', 'Two']);
});

test('tenant ownership and parent identifiers are not mass assignable', function () {
    $bot = new Bot;
    $bot->fill([
        'user_id' => 999,
        'public_id' => 'browser-controlled',
        'name' => 'Safe name',
    ]);

    $setting = new BotSetting;
    $setting->fill(['bot_id' => 999, 'welcome_message' => 'Welcome']);

    $question = new BotStarterQuestion;
    $question->fill(['bot_id' => 999, 'question' => 'Question', 'position' => 1]);

    $field = new BotPrechatField;
    $field->fill(['bot_id' => 999, 'key' => 'custom_topic']);

    expect($bot->getAttribute('user_id'))->toBeNull()
        ->and($bot->getAttribute('public_id'))->toBeNull()
        ->and($bot->name)->toBe('Safe name')
        ->and($setting->getAttribute('bot_id'))->toBeNull()
        ->and($question->getAttribute('bot_id'))->toBeNull()
        ->and($field->getAttribute('bot_id'))->toBeNull();
});

test('bot policy grants subscriber owners and denies cross tenant and admin access', function () {
    $owner = User::factory()->subscriber()->create();
    $otherSubscriber = User::factory()->subscriber()->create();
    $admin = User::factory()->admin()->create();
    $bot = Bot::factory()->for($owner)->create();

    foreach (['view', 'update', 'delete', 'train', 'manageEmbed'] as $ability) {
        expect(Gate::forUser($owner)->allows($ability, $bot))->toBeTrue()
            ->and(Gate::forUser($otherSubscriber)->denies($ability, $bot))->toBeTrue()
            ->and(Gate::forUser($admin)->denies($ability, $bot))->toBeTrue();
    }

    expect(Gate::forUser($owner)->allows('viewAny', Bot::class))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('create', Bot::class))->toBeTrue()
        ->and(Gate::forUser($admin)->denies('viewAny', Bot::class))->toBeTrue()
        ->and(Gate::forUser($admin)->denies('create', Bot::class))->toBeTrue();
});

test('bot schema never stores provider secrets', function () {
    $columns = array_merge(
        Schema::getColumnListing('bots'),
        Schema::getColumnListing('bot_settings'),
    );

    expect(array_intersect([
        'openai_api_key', 'api_key', 'secret', 'access_token', 'password',
    ], $columns))->toBeEmpty();
});
