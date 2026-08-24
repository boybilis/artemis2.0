<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Artemis 2.0</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            color: #1f2937;
            background: #f5f7fb;
            font-family: Arial, Helvetica, sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 430px;
            padding: 28px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.1);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }

        .brand-mark {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: linear-gradient(135deg, #2563eb, #7c3aed);
            color: #ffffff;
            font-weight: 800;
        }

        h1 {
            margin: 0;
            font-size: 24px;
        }

        p {
            margin: 6px 0 0;
            color: #64748b;
            line-height: 1.5;
        }

        .field {
            display: grid;
            gap: 7px;
            margin-top: 16px;
        }

        label {
            color: #64748b;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            outline: none;
        }

        input:focus {
            border-color: #93c5fd;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .12);
        }

        .btn {
            width: 100%;
            margin-top: 20px;
            padding: 11px 14px;
            border: 1px solid #2563eb;
            border-radius: 10px;
            background: #2563eb;
            color: #ffffff;
            font-weight: 800;
            cursor: pointer;
        }

        .note {
            margin-top: 16px;
            padding: 13px;
            border-radius: 12px;
            background: #eff6ff;
            color: #1e3a8a;
            border: 1px solid #bfdbfe;
            font-size: 14px;
        }

        .back-link {
            display: inline-block;
            margin-top: 16px;
            color: #2563eb;
            font-weight: 800;
            text-decoration: none;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <main class="login-card">
        <div class="brand">
            <div class="brand-mark">LC</div>
            <div>
                <h1>Admin Login</h1>
                <p>Placeholder screen for the future Laravel auth flow.</p>
            </div>
        </div>

        <form action="{{ route('admin.login.submit') }}" method="POST">
            @csrf
            
            @if ($errors->any())
                <div class="note" style="background: #fee2e2; border-color: #fca5a5; color: #991b1b; margin-bottom: 15px; margin-top: 0;">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            @if (session('error'))
                <div class="note" style="background: #fee2e2; border-color: #fca5a5; color: #991b1b; margin-bottom: 15px; margin-top: 0;">
                    {{ session('error') }}
                </div>
            @endif

            <div class="field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" placeholder="Administrator email" autocomplete="email" required value="{{ old('email') }}">
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" placeholder="••••••••" autocomplete="current-password" required>
            </div>
            <button class="btn" type="submit">Sign In</button>
        </form>

        <div class="note">
            Sign in with your Artemis 2.0 administrator account. Administrative activity is recorded in the security audit log.
        </div>

        <a class="back-link" href="/">← Back to Artemis 2.0</a>
    </main>
</body>
</html>
