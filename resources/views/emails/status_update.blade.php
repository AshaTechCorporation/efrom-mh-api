<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { padding: 20px; }
        .footer { margin-top: 20px; font-weight: bold; }
        ul { list-style-type: disc; padding-left: 20px; }
        li { margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <p>Dear {{ $data['requester_name'] ?? 'Requester' }},</p>
        
        <p>Please be informed that your request has been <strong>{{ $data['status'] ?? 'Processed' }}</strong>. Details are as follows:</p>
        
        <ul>
            <li><strong>Document Name:</strong> {{ $data['document_name'] ?? '-' }}</li>
            <li><strong>Status:</strong> {{ $data['status'] ?? '-' }}</li>
            <li><strong>Date:</strong> {{ $data['date'] ?? '-' }}</li>
            <li><strong>Remarks:</strong> {{ $data['remarks'] ?? '-' }}</li>
        </ul>
        
        <p>For more information, please visit: <a href="{{ $data['link'] ?? 'https://edms.meinhardt.net' }}">https://edms.meinhardt.net</a></p>
        
        <div class="footer">
            Regards,<br>
            MTL Notification<br>
            Meinhardt (Thailand) Ltd.
        </div>
    </div>
</body>
</html>
