<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>@config('app.name') Notification</title>
<style>
  body {
    font-family: Arial, Helvetica, sans-serif;
    background-color: #f4f6f8;
    margin: 0;
    padding: 0;
  }
  .container {
    max-width: 600px;
    background-color: #ffffff;
    margin: 30px auto;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
  }
  .header {
    text-align: center;
    font-size: 22px;
    font-weight: bold;
    color: #333333;
    margin-bottom: 10px;
  }
  .divider {
    height: 2px;
    background-color: #007bff;
    width: 100px;
    margin: 10px auto 20px;
  }
  .content {
    font-size: 15px;
    color: #333333;
    line-height: 1.6;
  }
  .info {
    background-color: #f7f9fc;
    padding: 12px;
    border-radius: 6px;
    margin-top: 15px;
    margin-bottom: 25px;
  }
  .info p {
    margin: 5px 0;
  }
  .button {
    display: inline-block;
    background-color: #007bff;
    color: #ffffff !important;
    text-decoration: none;
    padding: 10px 18px;
    border-radius: 6px;
    font-weight: bold;
  }
  .footer {
    font-size: 13px;
    color: #777777;
    text-align: center;
    margin-top: 25px;
  }
</style>
</head>
<body>
    <div class="container">
        <div class="header">{{ env('APP_NAME') }} Notification</div>
        <div class="divider"></div>
        <div class="content">
            {!! $bodyContent !!}
        </div>
        <div class="footer">
            <p>
                <span>This is an automated email from {{ env('APP_NAME') }}.</span>
                <br>
                <span>© {{ date('Y') }} {{ env('APP_NAME') }}. All rights reserved.</span>
            </p>
        </div>
    </div>
</body>
</html>
