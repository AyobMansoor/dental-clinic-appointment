<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password</title>
  <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
  <form class="modern-form" method="POST" onsubmit="sendOTP(event)">
    <div class="form-title">Forgot Password</div>
    <div class="form-body">
      <div class="input-group">
        <div class="input-wrapper">
          <input required placeholder="Enter your email" class="form-input" type="email" name="email" />
        </div>
      </div>
    </div>
    <button class="submit-button" type="submit">
      <span class="button-text">Send OTP</span>
    </button>
    <button type="button" class="submit-button" onclick="window.location.href='../login.html';">Cancel</button>
  </form>

  <script>
    function sendOTP(event) {
      event.preventDefault(); // منع إرسال النموذج بالطريقة التقليدية

      const email = document.querySelector('input[name="email"]').value;

      // إرسال البيانات إلى الخادم
      fetch('send_otp.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email: email }),
      })
        .then(response => response.json())
        .then(data => {
          if (data.status === 'success') {
            alert(data.message); // عرض رسالة النجاح
            window.location.href = 'verify_otp.php'; // الانتقال إلى صفحة التحقق من OTP
          } else {
            alert(data.message); // عرض رسالة الخطأ
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('An error occurred. Please try again.');
        });
    }
  </script>
</body>
</html>