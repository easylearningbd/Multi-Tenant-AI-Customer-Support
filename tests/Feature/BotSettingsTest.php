<?php

use App\Enums\BotTone;
use App\Enums\PrechatFieldType;
use App\Models\Bot;
use App\Models\BotPrechatField;
use App\Models\BotSetting;
use App\Models\BotStarterQuestion;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

function validBotSettingsPayload(Bot $bot): array
{
    return [
        'name' => $bot->name,
        'display_name' => $bot->display_name,
        'welcome_message' => 'How can our assistant help?',
        'prechat_enabled' => true,
        'tone' => BotTone::FRIENDLY->value,
        'primary_language' => 'en',
        'persona' => 'A concise product support specialist.',
        'fallback_message' => 'I could not find that answer. Would you like a person?',
        'offer_human_handoff' => true,
        'answer_only_from_knowledge_base' => true,
        'model_override' => null,
        'temperature' => '0.30',
        'max_output_tokens' => 600,
        'kb_confidence' => '0.650',
        'is_active' => false,
        'starter_questions' => [],
        'prechat_fields' => [],
    ];
}

test('guest is redirected and administrator is forbidden from bot settings routes', function () {
    $subscriber = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($subscriber)->create();

    $this->get(route('bots.settings.edit', $bot))->assertRedirect(route('login'));
    $this->put(route('bots.settings.update', $bot), [])->assertRedirect(route('login'));
    $this->delete(route('bots.destroy', $bot))->assertRedirect(route('login'));

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('bots.settings.edit', $bot))->assertForbidden();
    $this->actingAs($admin)->put(route('bots.settings.update', $bot), [])->assertForbidden();
    $this->actingAs($admin)->delete(route('bots.destroy', $bot))->assertForbidden();
});

test('subscriber cannot view update or delete another subscribers bot', function () {
    $subscriber = User::factory()->subscriber()->create();
    $other = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($other)->create();

    $this->actingAs($subscriber)->get(route('bots.settings.edit', $bot))->assertNotFound();
    $this->actingAs($subscriber)->put(route('bots.settings.update', $bot), validBotSettingsPayload($bot))->assertNotFound();
    $this->actingAs($subscriber)->delete(route('bots.destroy', $bot))->assertNotFound();

    expect($bot->fresh())->not->toBeNull();
});

test('settings page renders stored configuration safely with unavailable module states', function () {
    $subscriber = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($subscriber)->create([
        'display_name' => '<script>alert("preview")</script>',
    ]);
    BotSetting::factory()->for($bot)->create(['welcome_message' => 'Welcome from storage']);
    BotStarterQuestion::factory()->for($bot)->create(['question' => 'How does billing work?', 'position' => 1]);

    $this->actingAs($subscriber)
        ->get(route('bots.settings.edit', $bot))
        ->assertOk()
        ->assertSee(e($bot->display_name), escape: false)
        ->assertDontSee($bot->display_name, escape: false)
        ->assertSee('Welcome from storage')
        ->assertSee('How does billing work?')
        ->assertSee('No integration module is available yet.')
        ->assertSee('Assignment becomes available with the Phase 4 knowledge module.')
        ->assertSee('aria-current="page"', escape: false);
});

test('subscriber updates identity behavior questions and prechat fields atomically', function () {
    config()->set('neuraldesk.ai.allowed_chat_models', ['approved-model']);
    $subscriber = User::factory()->subscriber()->create();
    $other = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($subscriber)->create(['is_active' => false]);
    $plan = Plan::factory()->create();
    Subscription::factory()->for($subscriber)->for($plan)->create(['plan_snapshot' => $plan->subscriptionSnapshot()]);
    BotSetting::factory()->for($bot)->create();
    BotStarterQuestion::factory()->for($bot)->create(['question' => 'Old question', 'position' => 1]);
    $originalPublicId = $bot->public_id;
    $originalSlug = $bot->slug;

    $payload = array_replace(validBotSettingsPayload($bot), [
        'name' => 'Renewal Assistant',
        'display_name' => 'Renewal Help',
        'welcome_message' => 'Welcome to renewals.',
        'tone' => BotTone::EMPATHETIC->value,
        'primary_language' => 'bn',
        'model_override' => 'approved-model',
        'temperature' => '0.55',
        'max_output_tokens' => 900,
        'kb_confidence' => '0.725',
        'is_active' => true,
        'starter_questions' => [' First question ', 'Second question'],
        'prechat_fields' => [
            [
                'standard_key' => 'email',
                'label' => ' Email address ',
                'type' => PrechatFieldType::TEXT->value,
                'placeholder' => ' you@example.com ',
                'is_required' => true,
                'options_text' => '',
            ],
            [
                'standard_key' => '',
                'label' => 'Department',
                'type' => PrechatFieldType::SELECT->value,
                'placeholder' => null,
                'is_required' => false,
                'options_text' => "Sales\nSupport\nSales",
            ],
        ],
        'user_id' => $other->id,
        'slug' => 'browser-slug',
        'public_id' => 'browser-public-id',
    ]);

    $this->actingAs($subscriber)
        ->put(route('bots.settings.update', $bot), $payload)
        ->assertRedirect(route('bots.settings.edit', $bot))
        ->assertSessionHasNoErrors();

    $bot->refresh()->load(['setting', 'starterQuestions', 'prechatFields']);

    expect($bot->name)->toBe('Renewal Assistant')
        ->and($bot->display_name)->toBe('Renewal Help')
        ->and($bot->is_active)->toBeTrue()
        ->and($bot->user_id)->toBe($subscriber->id)
        ->and($bot->public_id)->toBe($originalPublicId)
        ->and($bot->slug)->toBe($originalSlug)
        ->and($bot->setting->tone)->toBe(BotTone::EMPATHETIC)
        ->and($bot->setting->primary_language)->toBe('bn')
        ->and($bot->setting->model_override)->toBe('approved-model')
        ->and($bot->starterQuestions->pluck('question')->all())->toBe(['First question', 'Second question'])
        ->and($bot->starterQuestions->pluck('position')->all())->toBe([1, 2])
        ->and($bot->prechatFields->pluck('key')->all())->toBe(['email', 'custom_department'])
        ->and($bot->prechatFields->first()->type)->toBe(PrechatFieldType::EMAIL)
        ->and($bot->prechatFields->last()->options)->toBe(['Sales', 'Support']);
});

test('updating a legacy bot creates exactly one settings row', function () {
    $subscriber = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($subscriber)->create();

    $this->actingAs($subscriber)
        ->put(route('bots.settings.update', $bot), validBotSettingsPayload($bot))
        ->assertSessionHasNoErrors();

    $this->actingAs($subscriber)
        ->put(route('bots.settings.update', $bot), validBotSettingsPayload($bot))
        ->assertSessionHasNoErrors();

    expect($bot->setting()->count())->toBe(1);
});

test('invalid nested configuration is rejected without changing stored records', function () {
    $subscriber = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($subscriber)->create(['name' => 'Original']);
    $setting = BotSetting::factory()->for($bot)->create(['temperature' => '0.30']);
    BotStarterQuestion::factory()->for($bot)->create(['question' => 'Original question', 'position' => 1]);
    $payload = array_replace(validBotSettingsPayload($bot), [
        'name' => 'Changed name',
        'tone' => 'invented',
        'primary_language' => 'xx',
        'temperature' => '2.50',
        'max_output_tokens' => 0,
        'kb_confidence' => '1.2',
        'starter_questions' => ['One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven'],
    ]);

    $this->actingAs($subscriber)
        ->from(route('bots.settings.edit', $bot))
        ->put(route('bots.settings.update', $bot), $payload)
        ->assertRedirect(route('bots.settings.edit', $bot))
        ->assertSessionHasErrors([
            'tone',
            'primary_language',
            'temperature',
            'max_output_tokens',
            'kb_confidence',
            'starter_questions',
        ], null, 'botSettings');

    expect($bot->fresh()->name)->toBe('Original')
        ->and($setting->fresh()->temperature)->toBe('0.30')
        ->and($bot->starterQuestions()->sole()->question)->toBe('Original question');
});

test('select prechat fields require options and standard fields cannot repeat', function () {
    $subscriber = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($subscriber)->create();
    $payload = array_replace(validBotSettingsPayload($bot), [
        'prechat_fields' => [
            ['standard_key' => '', 'label' => 'Department', 'type' => 'select', 'placeholder' => '', 'is_required' => false, 'options_text' => ''],
            ['standard_key' => 'email', 'label' => 'Email', 'type' => 'email', 'placeholder' => '', 'is_required' => true, 'options_text' => ''],
            ['standard_key' => 'email', 'label' => 'Backup email', 'type' => 'email', 'placeholder' => '', 'is_required' => false, 'options_text' => ''],
        ],
    ]);

    $this->actingAs($subscriber)
        ->put(route('bots.settings.update', $bot), $payload)
        ->assertSessionHasErrors([
            'prechat_fields.0.options',
            'prechat_fields.2.standard_key',
        ], null, 'botSettings');

    $this->assertDatabaseCount('bot_prechat_fields', 0);
});

test('subscriber deletion deactivates and soft deletes only the owned bot while retaining configuration', function () {
    $subscriber = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($subscriber)->create(['is_active' => true]);
    $setting = BotSetting::factory()->for($bot)->create();
    $question = BotStarterQuestion::factory()->for($bot)->create();
    $field = BotPrechatField::factory()->for($bot)->create();

    $this->actingAs($subscriber)
        ->delete(route('bots.destroy', $bot))
        ->assertRedirect(route('bots.index'));

    $this->assertSoftDeleted('bots', ['id' => $bot->id, 'is_active' => false]);
    $this->assertDatabaseHas('bot_settings', ['id' => $setting->id, 'bot_id' => $bot->id]);
    $this->assertDatabaseHas('bot_starter_questions', ['id' => $question->id, 'bot_id' => $bot->id]);
    $this->assertDatabaseHas('bot_prechat_fields', ['id' => $field->id, 'bot_id' => $bot->id]);
});
