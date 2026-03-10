<?php if (!defined('ABSPATH')) exit; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Certificado - <?php echo esc_html($course_name ?? 'Curso'); ?></title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background: #f4f1ea;
      font-family: Georgia, "Times New Roman", serif;
      color: #1f2937;
    }

    .presslms-cert {
      width: 1123px;
      min-height: 794px;
      margin: 20px auto;
      background: #fffdf8;
      border: 12px solid #c9a96e;
      box-shadow: 0 20px 60px rgba(0,0,0,.12);
      position: relative;
      padding: 60px 70px;
      box-sizing: border-box;
    }

    .presslms-cert__logo {
      text-align: center;
      margin-bottom: 20px;
    }

    .presslms-cert__logo img {
      max-height: 90px;
      max-width: 220px;
    }

    .presslms-cert__title {
      text-align: center;
      font-size: 44px;
      font-weight: bold;
      color: #8b5e34;
      margin: 20px 0 10px;
      letter-spacing: 1px;
    }

    .presslms-cert__subtitle {
      text-align: center;
      font-size: 18px;
      color: #6b7280;
      margin-bottom: 35px;
    }

    .presslms-cert__student {
      text-align: center;
      font-size: 40px;
      font-weight: bold;
      color: #111827;
      margin: 24px 0;
    }

    .presslms-cert__text {
      text-align: center;
      font-size: 20px;
      line-height: 1.8;
      max-width: 840px;
      margin: 0 auto 20px;
      color: #374151;
    }

    .presslms-cert__course {
      text-align: center;
      font-size: 28px;
      font-weight: bold;
      color: #1d4ed8;
      margin: 20px 0;
    }

    .presslms-cert__meta {
      text-align: center;
      font-size: 18px;
      color: #4b5563;
      margin-top: 12px;
    }

    .presslms-cert__footer {
      width: 100%;
      margin-top: 70px;
      display: flex;
      justify-content: center;
    }

    .presslms-cert__signature {
      text-align: center;
      min-width: 320px;
    }

    .presslms-cert__signature img {
      max-height: 80px;
      margin-bottom: 8px;
    }

    .presslms-cert__line {
      height: 1px;
      background: #9ca3af;
      margin-top: 10px;
    }

    .presslms-cert__label {
      margin-top: 8px;
      font-size: 16px;
      color: #374151;
    }
  </style>
</head>
<body>
  <div class="presslms-cert">
    <?php if (!empty($logo_url)): ?>
      <div class="presslms-cert__logo">
        <img src="<?php echo esc_url($logo_url); ?>" alt="Logo">
      </div>
    <?php endif; ?>

    <div class="presslms-cert__title">Certificado de Conclusão</div>
    <div class="presslms-cert__subtitle">Certificamos que</div>

    <div class="presslms-cert__student">
      <?php echo esc_html($student_name ?? 'Aluno'); ?>
    </div>

    <div class="presslms-cert__text">
      concluiu com êxito o curso
    </div>

    <div class="presslms-cert__course">
      <?php echo esc_html($course_name ?? 'Curso'); ?>
    </div>

    <?php if (!empty($description)): ?>
      <div class="presslms-cert__text">
        <?php echo wp_kses_post(wpautop($description)); ?>
      </div>
    <?php endif; ?>

    <div class="presslms-cert__meta">
      <strong>Duração:</strong> <?php echo esc_html($course_duration ?? ''); ?>
      <?php if (!empty($completion_date)): ?>
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <strong>Concluído em:</strong> <?php echo esc_html($completion_date); ?>
      <?php endif; ?>
    </div>

    <div class="presslms-cert__footer">
      <div class="presslms-cert__signature">
        <?php if (!empty($signature_url)): ?>
          <img src="<?php echo esc_url($signature_url); ?>" alt="Assinatura">
        <?php endif; ?>
        <div class="presslms-cert__line"></div>
        <div class="presslms-cert__label">Assinatura</div>
      </div>
    </div>
  </div>
</body>
</html>