<?php

namespace Tests\Feature\Jobs;

use App\Jobs\InboundRuleMatch;
use App\Models\InboundEmail;
use App\Models\InboundEmailRules;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InboundRuleMatchTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    /**
     * A basic feature test example.
     */
    public function test_find_matching_rule(): void
    {
        // Create a sample InboundEmail
        $email = InboundEmail::factory()->create([
            'headers' => 'Cc: Client Dispatch <Dispatch@calltheory.com>\n
Content-Type: multipart/mixed; boundary="0000000000008f3d5f062eade56d"\n
DKIM-Signature: v=1; a=rsa-sha256; c=relaxed/relaxed;        d=client-net.20230601.gappssmtp.com; s=20230601; t=1740174276; x=1740779076; darn=inbound.calltheory.com;        h=cc:to:subject:message-id:date:from:mime-version:from:to:cc:subject         :date:message-id:reply-to;        bh=ncvwBriWlXZ1YQrSakIlseq3pLvdtvbYYBOcprVqc7U=;        b=VjmUHEXEv2U1k8SPQLDUdmItFH9IsIUZsp5bZEn81eG8SJNhXOshqBdRVpFJPeiBmD         kktRAWEb3gao6J5USaimrdNoJqTINySXEIuItzrhEGBpFeCcUbpArrMzdCiV6kqLpqO0         b9yVwFU8yjb8HWATx4vdocBUEwXczXHf44XNlk1yymEs9lF7sJTnVBIGApOWqgo8KkZG         121Y+4j+NVSZn9L6S2SrIUKEcAgFcKzouNTipkSqMTuAxRmONqVM7TKSy19wanaCjmvS         mKA4xoOLCnrIRMehImR1qQSUKIYqEnlRck+Ba4ffkIRQ1Fm66keOx/TBDp3je/9DCRv0         gV4Q==\n
Date: Fri, 21 Feb 2025 16:44:25 -0500\n
From: Client Dispatch <Dispatch@calltheory.com>\n
MIME-Version: 1.0\n
Message-ID: <CAMMQ4_XF3ukNUMzP8Nz-2zBQmuYGo7nK7kOVjN1=M-Y-R-KFog@mail.gmail.com>\n
Received: from mail-oo1-f42.google.com (mxd [209.85.161.42]) by mx.sendgrid.net with ESMTP id 2GaFdUzeSOGThRp5xUIivQ for <1234JobList@inbound.calltheory.com>; Fri, 21 Feb 2025 21:44:36.897 +0000 (UTC)\n
Received: by mail-oo1-f42.google.com with SMTP id 006d021491bc7-5fc69795ecbso1230678eaf.1        for <1234JobList@inbound.calltheory.com>; Fri, 21 Feb 2025 13:44:36 -0800 (PST)\n
Subject: Client Pilots worksheet\n
To: Call Theory Answering Service CSV File <1234JobList@inbound.calltheory.com>\n
X-Gm-Features: AWEUYZmH18tVJkBhWGJnmAVkHjWIlgtQLuS6C9xkKhaiyOS_oUHZBLDSUlsUeEg\n
X-Gm-Gg: ASbGncsknd45rTD4xbWJgVwmaMZMFpeLuzPUovtiH7HVG6o5fkzRNl8az+e7OAePlG7\t2zbQ3OB21PmjzUxRDoogIj8M5PjzPHJ9nuB20HAH0BrufhZHvNtOdn2KnswPwysV1gMglEgdH4F\tX/PbhsPA==\n
X-Gm-Message-State: AOJu0YynQYIcuH87L3RD6ZJshHqZrfj/092qTu7hq4yd4+ytGZe0ZLcA\t6Q5fMAgSwrQXLWD+uVPljB9OqRBY1WAb8Qp8sYTUztR5WlFcgLu5zll/m6TOUHvD2w051ZNpm64\tscgObN2YAgdzekiqNhnzA43OT0WGN9c1tqZKQHqBUy8u/kZ7CoZ8=\n
X-Google-DKIM-Signature: v=1; a=rsa-sha256; c=relaxed/relaxed;        d=1e100.net; s=20230601; t=1740174276; x=1740779076;        h=cc:to:subject:message-id:date:from:mime-version:x-gm-message-state         :from:to:cc:subject:date:message-id:reply-to;        bh=ncvwBriWlXZ1YQrSakIlseq3pLvdtvbYYBOcprVqc7U=;        b=CfJ824lYm8BUgwBXR101VAH7mV+aFVzwHcCEuvwL1LNGT8G1etQNgMfEXfeoY4S/YQ         etO6l7jczeourMbPE8dOmFoW/Ju6uvss0zEP5mtXR8WgubnLPv9xPYWdUF94geEBCvUH         04UYRL/yJIVzbkckBDRXTxhzAj95IzulfHTlXfroHKQ9I47l/vVZechzcum3ww/jMKNg         glSnpjshgz23XXkny4k6NqHOoFo4RwMuuvzJ7bDaHH2SpBSzr96jHm+bZlaxjfWiICU2         VZJwT8OAIIFSlNDFe6WmJgjo1qWvIf2N4J0b3kvDDdDTKv7GQRTZiOKHluQsUOOOFxAp         lWqA==\n
X-Google-Smtp-Source: AGHT+IGrq4S9wzo7jQg98h+Lp9UhAGGYBPqVZSDKkL377e3BWWcNhLJu/al66AtI5rAmwl+DD4qModrM0IExtGOEqf8=\n
X-Received: by 2002:a05:6808:1587:b0:3f4:9ae:cd60 with SMTP id 5614622812f47-3f425a790a6mr2911095b6e.13.1740174275837; Fri, 21 Feb 2025 13:44:35 -0800 (PST)',
            'dkim' => '{@client-net.20230601.gappssmtp.com : pass}',
            'content_ids' => '{"f_m7fasuah0":"attachment1"}',
            'to' => 'Call Theory Answering Service CSV File <1234JobList@inbound.calltheory.com>',
            'from' => 'Client Dispatch <Dispatch@calltheory.com>',
            'sender_ip' => '209.85.161.42',
            'envelope' => '{"to":["1234JobList@inbound.calltheory.com"],"from":"chris@calltheory.com"}',
            'attachments' => '1',
            'subject' => 'Client worksheet',
            'spam_report' => null,
            'spam_score' => null,
            'attachment_info' => '{"attachment1":{"filename":"Worksheet 250221.csv","name":"Worksheet 250221.csv","charset":"US-ASCII","type":"text/csv","content-id":"f_m7fasuah0"}}',
            'charsets' => '{"to":"UTF-8","from":"UTF-8","subject":"UTF-8","cc":"UTF-8","text":"utf-8","html":"utf-8","filename":"UTF-8"}',
            'spf' => null,
            'text' => null,
            'html' => '<div dir="ltr"><br></div>',
            'processed_at' => null,
            'ignored_at' => '2025-02-21 21:44:40',
            'inbound_email_rules_id' => null,
            'created_at' => '2025-02-21 21:44:37',
            'updated_at' => '2025-02-21 21:44:40',
        ]);

        // Create a sample InboundEmailRules
        $rule = InboundEmailRules::factory()->create([
            'name' => '1234 Job Listing',
            'rules' => '{"to": {"contains": ["1234JobList@inbound.calltheory.com"]}, "attachment": {"ends_with": [".csv"]}}',
            'enabled' => 1,
            'mergecomm_trigger_id' => null,
            'category' => 'database:replace:1234ClientJobList',
            'created_at' => '2024-11-07 23:13:32',
            'updated_at' => '2024-11-07 23:13:32',
            'account' => '1234',
        ]);

        // Create an instance of the job
        $job = new InboundRuleMatch($email);

        // Call the findMatchingRule method
        $matches = $job->findMatchingRule();

        // Assert that the rule matches
        $this->assertArrayHasKey($rule->id, $matches);
    }
}
