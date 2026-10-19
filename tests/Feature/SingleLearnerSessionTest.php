<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingleLearnerSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_login_revokes_old_session_and_old_logout_cannot_revoke_new_login(): void
    {
        $user=User::factory()->create(['is_active'=>true,'is_admin'=>false]);
        $credentials=['email'=>$user->email,'password'=>'password'];
        $this->postJson('/api/auth/login',$credentials)->assertOk();
        $old=session('learner_login_token');
        $this->assertSame($old,$user->fresh()->active_login_token);
        $this->postJson('/api/auth/login',$credentials)->assertOk();
        $new=session('learner_login_token');
        $this->assertNotSame($old,$new);
        $this->getJson('/api/auth/session')->assertOk()->assertJsonPath('user.email',$user->email);
        $this->withSession(['learner_login_token'=>$old])->postJson('/api/auth/logout')->assertUnauthorized()->assertJsonPath('code','session_replaced');
        $this->assertSame($new,$user->fresh()->active_login_token);
        $this->actingAs($user->fresh())->withSession(['learner_login_token'=>$new])->getJson('/api/question-flags/unread')->assertOk();
    }

    public function test_revoked_learner_cannot_read_or_mutate_protected_data(): void
    {
        $user=User::factory()->create(['is_admin'=>false]);
        $user->forceFill(['active_login_token'=>str_repeat('a',64)])->save();
        $this->actingAs($user)->withSession(['learner_login_token'=>str_repeat('b',64)])
            ->postJson('/api/question-flags',['reason'=>'Blocked stale session'])->assertUnauthorized()->assertJsonPath('code','session_replaced');
        $this->assertDatabaseCount('question_flags',0);
    }

    public function test_existing_legacy_session_is_adopted_once_and_refresh_keeps_it(): void
    {
        $user=User::factory()->create(['is_admin'=>false,'is_active'=>true]);
        $this->actingAs($user)->getJson('/api/auth/session')->assertOk();
        $token=session('learner_login_token');
        $this->assertNotEmpty($token);
        $this->getJson('/api/auth/session')->assertOk();
        $this->assertSame($token,session('learner_login_token'));
        $this->assertSame($token,$user->fresh()->active_login_token);
        $this->withSession(['learner_login_token'=>'missing-or-stale'])->getJson('/api/auth/session')->assertUnauthorized();
    }

    public function test_admin_and_instructor_sessions_are_not_restricted_and_token_is_hidden(): void
    {
        foreach(['admin','instructor'] as $role){
            $user=User::factory()->create(['role'=>$role,'is_admin'=>$role==='admin']);
            $this->actingAs($user)->getJson('/api/auth/session')->assertOk();
            $this->assertNull($user->fresh()->active_login_token);
        }
        $this->assertArrayNotHasKey('active_login_token',$user->toArray());
    }
}
