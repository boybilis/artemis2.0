<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your Voucher Code</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #0a0a0f;
            color: #ffffff;
            margin: 0;
            padding: 40px 20px;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background-color: #12121a;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: 40px 30px;
            text-align: center;
        }
        .logo {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #5b9bf6;
        }
        h2 {
            margin: 0 0 10px;
            font-size: 22px;
            font-weight: 600;
        }
        p {
            color: #88889a;
            font-size: 14px;
            line-height: 1.5;
            margin: 0 0 30px;
        }
        .success-box {
            background-color: rgba(91, 155, 246, 0.05);
            border: 1px solid rgba(91, 155, 246, 0.2);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .icon {
            font-size: 32px;
            margin-bottom: 15px;
            display: block;
        }
        .success-text {
            color: #ffffff;
            margin-bottom: 15px;
            font-size: 15px;
        }
        .voucher-code {
            background: linear-gradient(135deg, #9b5de5, #5b9bf6);
            color: #ffffff;
            font-family: monospace;
            font-size: 22px;
            font-weight: bold;
            padding: 15px 20px;
            border-radius: 8px;
            letter-spacing: 2px;
            margin: 0;
        }
        .note {
            color: #88889a;
            font-size: 13px;
            margin-top: 15px;
            margin-bottom: 0;
        }
        .footer {
            margin-top: 40px;
            font-size: 12px;
            color: #444458;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">Artemis 2.0</div>
        <h2>Payment Successful!</h2>
        <p>Thank you for investing in your future with Artemis 2.0 Certification. Your payment has been confirmed.</p>
        
        <div class="success-box">
            <p class="success-text">Here is your voucher code:</p>
            <p class="voucher-code">{{ $voucher->code }}</p>
            <p class="note">Copy this code and enter it on the dashboard to activate access to the available review courses and assessments.</p>
        </div>

        <div class="footer">
            © {{ date('Y') }} Artemis 2.0. All rights reserved.
        </div>
    </div>
</body>
</html>
