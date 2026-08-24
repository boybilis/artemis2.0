<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Topic;
use App\Models\Lesson;
use App\Models\QuizQuestion;
use App\Models\Voucher;
use App\Models\Certificate;

class CSSMAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic database structures
        $topic = Topic::create([
            'title' => 'CSS Colors',
            'sort_order' => 1
        ]);

        Lesson::create([
            'topic_id' => $topic->id,
            'title' => 'Colors, RGB, HEX, HSL',
            'video_url' => 'https://youtube.com/embed/123',
            'notes' => 'Some lesson study notes.'
        ]);

        QuizQuestion::create([
            'topic_id' => $topic->id,
            'question' => 'Which is correct?',
            'options' => ['Option A', 'Option B'],
            'answer' => 0
        ]);

        // Seed a final exam question
        QuizQuestion::create([
            'topic_id' => null,
            'question' => 'What is CSS?',
            'options' => ['Cascading Style Sheets', 'Other'],
            'answer' => 0
        ]);
    }

    public function test_auth_registration_and_login()
    {
        $response = $this->postJson('/api/auth/register', [
            'su-fname' => 'John',
            'su-lname' => 'Doe',
            'su-email' => 'john@example.com',
            'su-bdate' => '05/19/1995',
            'su-afftype' => 'school',
            'su-affname' => 'University of PHP',
            'su-phone' => '09123456789',
            'su-password' => 'secret123'
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'email' => 'john@example.com',
                    'firstName' => 'John'
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'name' => 'John Doe'
        ]);

        // Test login
        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'john@example.com',
            'password' => 'secret123'
        ]);

        $loginResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // Test session endpoint
        $sessionResponse = $this->getJson('/api/auth/session');
        $sessionResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'email' => 'john@example.com'
                ]
            ]);
    }

    public function test_syllabus_topics_and_progress()
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('secret123'),
            'is_admin' => false,
            'is_active' => true
        ]);

        $this->actingAs($user);

        // Get syllabus
        $response = $this->getJson('/api/topics');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'topics');

        // Submit quiz attempt
        $topic = Topic::first();
        $quizResponse = $this->postJson('/api/quiz/attempt', [
            'topic_id' => $topic->id,
            'score' => 1,
            'total' => 1,
            'passed' => true
        ]);

        $quizResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'completedTopics' => [$topic->id]
            ]);

        $this->assertDatabaseHas('user_progress', [
            'user_id' => $user->id,
            'topic_id' => $topic->id
        ]);
    }

    public function test_voucher_purchase_redemption_and_final_exam()
    {
        $user = User::create([
            'name' => 'Alice Cooper',
            'first_name' => 'Alice',
            'last_name' => 'Cooper',
            'email' => 'alice@example.com',
            'password' => bcrypt('secret123'),
            'is_admin' => false,
            'is_active' => true
        ]);

        $this->actingAs($user);

        // Buy voucher
        $buyResponse = $this->postJson('/api/voucher/buy');
        $buyResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $code = $buyResponse->json('code');
        $this->assertNotNull($code);

        // Verify voucher
        $verifyResponse = $this->postJson('/api/voucher/verify', ['code' => $code]);
        $verifyResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // Redeem voucher
        $redeemResponse = $this->postJson('/api/voucher/redeem', ['code' => $code]);
        $redeemResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('vouchers', [
            'code' => $code,
            'used' => true,
            'used_by' => $user->id
        ]);

        // Get exam questions
        $examResponse = $this->getJson('/api/exam/questions');
        $examResponse->assertStatus(200)
            ->assertJsonCount(1, 'questions');

        // Submit exam answers
        $submitResponse = $this->postJson('/api/exam/submit', [
            'voucher_code' => $code,
            'answers' => [0 => 0] // Correct choice index
        ]);

        $submitResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'passed' => true,
                'score' => 1,
                'total' => 1
            ]);

        $this->assertDatabaseHas('certificates', [
            'user_id' => $user->id
        ]);
    }
}
