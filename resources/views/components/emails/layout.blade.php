<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        .eyebrow { color:#9a7954; font-size:11px; font-weight:bold; letter-spacing:1.6px; margin:0; text-transform:uppercase; }
        h1 { color:#2f2923; font-family:Georgia, 'Times New Roman', serif; font-size:30px; line-height:1.18; margin:10px 0 16px; }
        .lead, .closing { color:#584c40; font-size:15px; line-height:1.65; margin:0; }
        .closing { margin-top:26px; }
        @media only screen and (max-width: 620px) { .email-content { padding:30px 24px !important; } .email-header, .email-footer { padding-left:24px !important; padding-right:24px !important; } h1 { font-size:27px !important; } }
    </style>
</head>
<body style="margin:0; padding:0; background-color:#eee3d1; color:#30271f; font-family:Arial, Helvetica, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#eee3d1;"><tr><td align="center" style="padding:32px 16px;">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:620px; background:#fffdf9;">
            <tr><td class="email-header" style="background:#1e3133; padding:30px 42px 28px; text-align:center;">
                <div style="font-family:Georgia, 'Times New Roman', serif; color:#f4eadb; font-size:30px; line-height:1; letter-spacing:3px;">🌲🌲🌲</div>
                <div style="font-family:Georgia, 'Times New Roman', serif; color:#fff9ee; font-size:20px; font-weight:bold; letter-spacing:2px; margin-top:10px;">WILD &amp; WONDER</div>
                <div style="color:#d9c4a5; font-size:10px; letter-spacing:2px; margin-top:8px; text-transform:uppercase;">Cabins in the heart of nature</div>
            </td></tr>
            <tr><td class="email-content" style="padding:42px 42px 30px;">{{ $slot }}</td></tr>
            <tr><td class="email-footer" style="background:#1e3133; padding:24px 42px; text-align:center;">
                <div style="color:#f4eadb; font-size:13px;"><a href="https://www.rockycabinsretreat.com" style="color:#f4eadb; text-decoration:underline;">www.rockycabinsretreat.com</a></div>
                <div style="color:#c9b79e; font-size:11px; margin-top:8px;">Where wild meets wonder.</div>
            </td></tr>
        </table>
    </td></tr></table>
</body>
</html>
