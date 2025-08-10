<?php
return [

    /**
     * You can generate API keys here: https://cloudconvert.com/dashboard/api/v2/keys.
     */

    'api_key' => env('CLOUDCONVERT_API_KEY', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxIiwianRpIjoiNmZiNjk3NTExYWU3MmFiZWI2MTEwMmFlZGZkMDQ2NGI1MWQwNjU2YmU5MzU4M2U5Y2JhYzgwNDNmZGJhNmQ5YzE3OGU5ZjcwOTY4MjllMGQiLCJpYXQiOjE3NTQ2NjEyOTkuNTY1MzM5LCJuYmYiOjE3NTQ2NjEyOTkuNTY1MzQsImV4cCI6NDkxMDMzNDg5OS41NjAwNDMsInN1YiI6IjcyNjE3ODM4Iiwic2NvcGVzIjpbInByZXNldC53cml0ZSIsInByZXNldC5yZWFkIiwid2ViaG9vay53cml0ZSIsIndlYmhvb2sucmVhZCIsInRhc2sud3JpdGUiLCJ0YXNrLnJlYWQiLCJ1c2VyLndyaXRlIiwidXNlci5yZWFkIl19.oMo6mS4Q1zUqZhscj3f_IkJzFn8N0ohyQ2-ba9mS1YpX3eccm3zQwmTWju3U6iDCQZnvjEYf0YYIqxCvF_Vr8I0-pj38dxWBjWTAY4GmupeSmfXuuUB9kYv03yItkPA0tFzkRIapskA43XjRbVdaFzfEdy5AHhOCZSEwjK7xVAbnmQIMDnFGyhwD3ZF9UPKuBGiNFvB5Q9vTcY5inbr4RLDjUhvh90H7ux3G3uF8aO-SeAXeGKyPJkzOfSlEwTIw_lQDsEe_TtkE8UOyfyWx_yUmRNC6Vv68V-2XTJ4zBEYW1Ln0lqSxWEGaLFPKEuoBWdkrPfDoncoLqK7b5u6YuWfXIq91WzxhKhTmI6kh96DYKvVmKkVaPcyrA6_xVlph9cJLPImIG1CP3_frFcnCxr6r-Pf4lPbmXLYawjn7AMVzpfJS9m0eWnMFjBko87dXe-X4pmHlUhoS31YLPKAR2aa1modu-jEh4G2fsm-xir0AfWRE4e_sEh9qj36V5aietvY9rJ55D0KH_JDX_OLBK0nPlX8OAAeju2akER0fTVTwAWNenQyDblMYfKQ_vBAV_AU1yuAJL-LoCEDG4crfg0qgzu6CClj-jEFLHkZo8la4GAggnDEdEOEmuZdM768HmdO88x2CeDizU_VUi_0Gi0eviBBrapOJrmu71FGkL2A'),

    /**
     * Use the CloudConvert Sanbox API (Defaults to false, which enables the Production API).
     */
    'sandbox' => env('CLOUDCONVERT_SANDBOX', false),

    /**
     * You can find the secret used at the webhook settings: https://cloudconvert.com/dashboard/api/v2/webhooks
     */
    'webhook_signing_secret' => env('CLOUDCONVERT_WEBHOOK_SIGNING_SECRET', ''),

];
