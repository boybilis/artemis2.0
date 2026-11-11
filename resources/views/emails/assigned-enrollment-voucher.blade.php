<p>Hello {{ $voucher->assignedLearner->name }},</p>
<p>An administrator issued you a GRA Test Prep enrollment voucher for <strong>{{ $voucher->batch->name }}</strong> ({{ $voucher->batch->code }}).</p>
<p>Your single-use code: <strong>{{ $voucher->code }}</strong></p>
<p>Sign in with this email address, find the batch in All Courses, and select <strong>Use Code</strong>. Enter the voucher code to activate your enrollment.</p>
<p>This code is assigned to your account and cannot be used by another learner or for another batch.</p>
