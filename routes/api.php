<?php

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;


use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SchemeController;
use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\PayinOnboardedBankController;
use App\Http\Controllers\Api\PayoutOnboardedBankController;
use App\Http\Controllers\Api\TicketHelpDeskController;
use App\Http\Controllers\Api\BeneficiaryDetailController;
use App\Http\Controllers\Api\Auth\ManagePasswordController;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;

//payin 
use App\Http\Controllers\Api\Payin\AllPayinController;
use App\Http\Controllers\Api\Payin\Dashboard_PayinController;
use App\Http\Controllers\Api\Payin\PayhaltController;
use App\Http\Controllers\Api\Payin\VPAtoINTENTController;
use App\Http\Controllers\Api\Payin\nxtController;
use App\Http\Controllers\Api\Payin\CommonPayinController;
use App\Http\Controllers\Api\Payin\AirpayStatusapiPayinController;
use App\Http\Controllers\Api\Payin\RazorpayStatusPayinController;
use App\Http\Controllers\Api\Payin\RiseXpayStatusPayinController;
use App\Http\Controllers\Api\Payin\ShaymavenueStatusPayinController;



//PayOut
use App\Http\Controllers\Api\Payout\PayoutBalanceController;
use App\Http\Controllers\Api\Payout\BusyBoxController;
use App\Http\Controllers\Api\Payout\PayoutController;
use App\Http\Controllers\Api\Payout\CommanPayoutController;
use App\Http\Controllers\Api\Payout\CashfreepayoutController;
use App\Http\Controllers\Api\Payout\BridgStatusPayoutController;
use App\Http\Controllers\Api\Payout\Dashboard_PayoutController;
use App\Http\Controllers\Api\Payout\E2payStatusapiPayoutController;

//callback Api
use App\Http\Controllers\Api\Callback\PayinCallback\PayinCallbackController;
use App\Http\Controllers\Api\Callback\PayoutCallback\PayoutCallbackController;


//chargeback
use App\Http\Controllers\Api\Payin\ChargebackController;



// user register
use App\Http\Controllers\Api\MerchantController;


//kyc-onboarding
use App\Http\Controllers\Api\GstController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\EmailOtpController;
use App\Http\Controllers\Api\BankVerificationController;
use App\Http\Controllers\Api\DigilockerController;
use App\Http\Controllers\Api\VideoKycController;
use App\Http\Controllers\Api\MerchantKycController;
use App\Http\Controllers\Api\MerchantKycDraftController;


Route::post('/payin/upi/request', [CommonPayinController::class, 'generateUpiQr']);


Route::post('/vkyc/session',            [VideoKycController::class, 'createSession']);
Route::post('/vkyc/{session_id}/start', [VideoKycController::class, 'markStarted']);
Route::post('/vkyc/{session_id}/upload',[VideoKycController::class, 'uploadVideo']);
Route::get('/vkyc/{session_id}/status', [VideoKycController::class, 'getStatus']);

Route::post('/digilocker/fetch-aadhaar',   [DigilockerController::class, 'fetchAadhaar']);
Route::post('/digilocker/init-aadhaar-pan',[DigilockerController::class, 'initAadhaarPan']);
Route::post('/digilocker/fetch-pan',       [DigilockerController::class, 'fetchPan']);
Route::post('/digilocker/fetch-documents', [DigilockerController::class, 'fetchDocuments']);
Route::post('/bank/account/verification-advance', [BankVerificationController::class, 'verifyAdvance']);
Route::post('/otp/send-email',         [EmailOtpController::class, 'sendEmailOtp']);
Route::post('/otp/send-custom-mobile', [OtpController::class, 'sendCustomMobileOtp']);
Route::post('/gst/advance-verify',     [GstController::class, 'advanceVerify']);

Route::post('/upload', function (Request $request) {
    // Validate the file
    $request->validate([
        'file' => 'required|file|max:10240', // max 10 MB
    ]);

    // Store the file in storage/app/uploads
    $path = $request->file('file')->store('uploads');

    return response()->json([
        'message' => 'File uploaded successfully',
        'path' => $path,
    ]);
});
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});


Route::post('create-merchant',   [MerchantController::class, 'createMerchant']);
Route::post('kyc-merchant',      [MerchantController::class, 'merchantKyc']);
Route::post('update-merchant-scheme', [MerchantController::class, 'updateMerchantKyc']);
Route::post('storeMerchant',     [MerchantController::class, 'storeMerchant']);
Route::post('send-otp-mail',     [MerchantController::class, 'sendOTP']);
Route::post('/verify-email-otp', [MerchantController::class, 'verifyEmailOtp']);



// --------------------
// Protected Routes
// --------------------
// Route::get('credentials',[UserController::class,'showCredentials']);
Route::middleware(['auth:sanctum'])->group(function () {
    //User/Merchant
    Route::post('delete-merchant/{id}', [UserController::class, 'deleteMerchant']);
    Route::post('onboard-merchant',     [UserController::class, 'onboardMerchant']);
    Route::get('show-merchant/{id?}',   [UserController::class, 'showMerchant']);
    Route::post('update-merchant/{id?}',[UserController::class, 'updateMerchant']);
    Route::put('update-user-statuses',  [UserController::class, 'updateUserStatuses']);
    Route::get('get-merchants',         [UserController::class, 'getMerchants']);
    Route::post('payin-settlement',     [UserController::class, 'managePayinWallet']);
    Route::post('payout-load-wallet',   [UserController::class, 'managePayoutWallet']);
    Route::post('payout-take-back',     [UserController::class, 'takeBackFromPayoutWallet']);
    Route::post('create-fund-request',  [UserController::class, 'createFundRequest']);
    Route::post('approve-fund-request', [UserController::class, 'approveFundRequest']);
    Route::put('payin-payout-statuses', [UserController::class, 'payinPayoutStatuses']);
    //Beneficiary Details
    Route::post('store-beneficiary-detail', [BeneficiaryDetailController::class, 'storeBeneficiaryDetails']);
    Route::get('beneficiary-List',          [BeneficiaryDetailController::class, 'BeneficiaryDetailsList']);
    Route::post('/delete-Beneficiary/{id?}',[BeneficiaryDetailController::class, 'deleteBeneficiaryDetails']);
    Route::any('/update-user-payin-bank',   [UserController::class, 'updateUserPayinBank']);
    //Mid credentials 
    Route::get('credentials',             [UserController::class,'showCredentials']);
    Route::post('update-credential',      [UserController::class, 'updateMerchantCredential']);
    Route::post('add-credential',         [UserController::class, 'addCredential']);
    Route::post('delete-credential/{id?}',[UserController::class, 'deleteCredential']);
    //Scheme
    Route::get('get-scheme',           [SchemeController::class, 'getScheme']);
    Route::post('create-scheme',       [SchemeController::class, 'createScheme']);
    Route::get('show-scheme/{id}',     [SchemeController::class, 'showScheme']);
    Route::post('update-scheme/{id}',  [SchemeController::class, 'updateScheme']);
    Route::post('delete-scheme/{id}',  [SchemeController::class, 'deleteScheme']);
    Route::post('update-scheme-status',[SchemeController::class, 'updateSchemeStatus']);
    //Auth Api Token
    Route::get('get-tokens',        [AuthTokenController::class, 'getTokens']);
    Route::post('generate-token',   [AuthTokenController::class, 'generateAuthToken']);
    Route::post('delete-token/{id}',[AuthTokenController::class, 'deleteAuthToken']);
    //Onboard PayIn Bank
    Route::post('/onboard-payinbank',        [PayinOnboardedBankController::class, 'OnboardPayInBank']);
    Route::post('/update-payin-bank-status', [PayinOnboardedBankController::class, 'updatePayinBankStatus']);
    Route::post('/update-bank-status/',      [PayinOnboardedBankController::class, 'updateOnboardedBankStatus']);
    Route::post('/delete-payinbank/{id}',    [PayinOnboardedBankController::class, 'DestroyPayInBank']); // Delete
    Route::get('/payinbanks-List',           [PayinOnboardedBankController::class, 'ListPayInBanks']); //bank list payin
    //Onboard PayOut Bank
    Route::post('/onboard-payoutbank',        [PayoutOnboardedBankController::class, 'OnboardPayOutBank']);
    Route::post('/update-payout-bank-status', [PayoutOnboardedBankController::class, 'updatePayoutBankStatus']);
    Route::post('/delete-payoutbank/{id}',    [PayoutOnboardedBankController::class, 'DestroyPayOutBank']); // Delete
    Route::get('/payoutbanks-List',           [PayoutOnboardedBankController::class, 'ListPayOutBanks']); //bank list payouy
    //Authentication
    Route::post('/change-password', [ManagePasswordController::class, 'changePassword'])->name('change.password');
    // Reports API's
    Route::post('/create-report',         [ReportController::class, 'createReport']);
    Route::get('/reportrecords-List',     [ReportController::class, 'ReportRecordsList']);
    Route::any('/txn-records-List',       [ReportController::class, 'Txnreport']);
    Route::any('/collection-record',      [ReportController::class, 'CollectionRecord']);
    Route::any('/collection-summary',     [ReportController::class, 'CollectionSummary']);
    Route::any('/collection-statuscounts',[ReportController::class, 'CollectionStatusCounts']);
    Route::any('/collection-monthwise',   [ReportController::class, 'CollectionMonthwise']);
    Route::any('/collection-cashfree',    [ReportController::class, 'CashfreeBalance']);
    Route::any('/Merchant-Collection',    [ReportController::class, 'MerchantCollection']);
    Route::any('/Merchant-Records',       [ReportController::class, 'MerchantRecords']);   
    Route::post('/chargeback',            [ReportController::class, 'chargeback']);
    //TicketHelpDesk
    Route::get('/get-tickets',         [TicketHelpDeskController::class, 'getTickets']);
    Route::post('/store-ticket',       [TicketHelpDeskController::class, 'storeTicket']);
    Route::get('/show-ticket/{id}',    [TicketHelpDeskController::class, 'showTicket']);
    Route::post('/update-ticket/{id}', [TicketHelpDeskController::class, 'updateTicket']);
    Route::post('/delete-ticket/{id}', [TicketHelpDeskController::class, 'deleteTicket']);
    Route::post('/manage-status-and-priority', [TicketHelpDeskController::class, 'manageStatusAndPriority']);
    
    //Dashboard payIN API
    Route::post('/Airpay/request',     [Dashboard_PayinController::class, 'generate_Airpay_UPIQR']);
    Route::any('AP/payin/checkstatus', [Dashboard_PayinController::class, 'check_status']);
    //Dashboard payOUT API 
    Route::post('/dashboard-payou/request', [Dashboard_PayoutController::class, 'Dashboard_payoutRequest']);
    
});


// --------------------
// Public Authentication Routes
// --------------------
Route::post('/register',       [RegisteredUserController::class, 'store'])->name('register');
Route::post('/login',          [AuthenticatedSessionController::class, 'store'])->name('login');
Route::post('/forgot-password',[PasswordResetLinkController::class, 'store'])->name('password.email');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
// Email verification routes (protected, user must be logged in)
Route::middleware('auth:sanctum')->group(function () {
Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])->name('verification.send');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
// Verify email (signed + throttled)
Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth:sanctum', 'signed', 'throttle:6,1'])
    ->name('verification.verify');
//charge back
Route::any('/chargeback',         [ChargebackController::class, 'Chargeback_record']);
Route::any('/reverse/chargeback', [ChargebackController::class, 'Reverse_chargeback']);

// --------------------
// Callback Apis
// --------------------
Route::group(['prefix'=> 'callback/update'], function() {
    
    
    // payin callback
    Route::any('prod/airpaycallbkp',      [PayinCallbackController::class, 'airpaycallbkp']);
    Route::any('prod/omishacallbkp',      [PayinCallbackController::class, 'omishacallbkp']);
    Route::any('prod/ebookcallbkp',       [PayinCallbackController::class, 'ebookcallbkp']);
    Route::any('prod/evahcallbkp',        [PayinCallbackController::class, 'evahcallbkp']);
    Route::any('prod/soulfulcallbkp',     [PayinCallbackController::class, 'soulfulcallbkp']);
    Route::any('prod/nxtcallbkp',         [PayinCallbackController::class, 'nxtcallbkp']);
    Route::any('prod/E2Paycallbkp',       [PayinCallbackController::class, 'E2Paycallbkp']);
    Route::any('prod/e2paycallbackpayin', [PayinCallbackController::class, 'e2paycallback']);
    Route::any('prod/ebookpaytmcallbkp',  [PayinCallbackController::class, 'ebookpaytmcallback']);
    Route::any('prod/riseXcallbkp',       [PayinCallbackController::class, 'riseXcallbkp']);
    Route::any('prod/shaymavenuecallbkp', [PayinCallbackController::class, 'Shaymavenuecallbkp']);
 
    
    // payout callback
    Route::any('prod/cashfreecallbkp',   [PayoutCallbackController::class, 'cashfreeCallback']);
    Route::any('prod/e2paycallbkp',      [PayoutCallbackController::class, 'e2payCallback']);
    Route::any('prod/e2payVanCallback',  [PayoutCallbackController::class, 'e2payVanCallback']);
    Route::any('prod/bridgmoneycallbkp', [PayoutCallbackController::class, 'bridgMoneyCallback']);
    Route::any('prod/Shaymavenuecallbkp',[PayoutCallbackController::class, 'shaymavenueCallback']);
    
});

Route::post('/callback/ntt',     [PayinCallbackController::class, 'nttcallback']);
Route::post('/callback/razorpay',[PayinCallbackController::class, 'razorpayCallback']);



//PayHalt Api for PayIN
Route::group(['prefix' => 'ph/payin'], function(){
    Route::post('/request', [PayhaltController::class, 'Generate_request']);
});
Route::post('/payout/payhalt', [PayoutController::class, 'payhaltPayout']);



//Airpay api for payin
Route::group(['prefix' => 'payin'], function(){  
    Route::any('/status', [AllPayinController::class, 'check_status']);
    Route::any('/status/update/DB', [AirpayStatusapiPayinController::class, 'Airpay_payin_status']);
    //payin API
    Route::post('/request', [AllPayinController::class, 'generate_Airpay_UPIQR']);
});



// nxt payin
Route::group(['prefix' => 'nxt/payin'], function(){  
    Route::post('/nxt_pay', [nxtController::class, 'nxt_intent']);
});



//Busybox Api for Payout
Route::group(['prefix' => 'bb/payout'], function(){
    Route::any('/request',      [BusyBoxController::class, 'payoutRequest']);
    Route::post('/upi/request', [BusyBoxController::class, 'upiRequest']);
});

Route::group(['prefix' => 'payout'], function(){
    Route::any('/request', [CommanPayoutController::class, 'payout_request']);
    Route::any('/status',  [CommanPayoutController::class, 'payout_status']);
});


//Cashfree Api for Payout
Route::group(['prefix' => 'CF/payout'], function(){
    Route::post('/payment/request', [CashfreepayoutController::class, 'payment_request']);
    Route::post('/upi/request',     [CashfreepayoutController::class, 'upi_request']);
    Route::post('/status',          [CashfreepayoutController::class, 'status']);
});



Route::get('/payoutbalance',[PayoutBalanceController::class,'payout_balance']);


//VPA to INTENT
Route::any('/vpa-intent', [VPAtoINTENTController::class, 'vpaToIntent']);

Route::get('/e2pay/status',[E2payStatusapiPayoutController::class, 'E2pay_payout_status']);
Route::get('/BM/status',   [BridgStatusPayoutController::class, 'bridgMoneyPayoutStatus']);
Route::any('/RZ/status',   [RazorpayStatusPayinController::class, 'razorpayPayinStatus']);
Route::any('/RX/status',   [RiseXpayStatusPayinController::class, 'RiseXpay_payin_status']);
Route::any('/SV/status',   [ShaymavenueStatusPayinController::class, 'Shaymavenue_payin_status']);




// --------------------
// KYC Routes
// --------------------

// Route::get('/merchant-kyc-documents/{user_id}', [MerchantKycController::class, 'listDocuments']);
// Route::post('/document-approve',[MerchantKycController::class, 'documentApprove']);
// Route::post('/document-reject', [MerchantKycController::class, 'documentReject']);

// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('/me', [AuthenticatedSessionController::class, 'me']);
    
//     Route::get('/merchant-kyc-documents/{user}', [MerchantKycController::class, 'documents']);
//     Route::post('/merchant/reupload/document',   [MerchantKycController::class, 'reuploadDocument']);
//     Route::post('/merchant/reupload/video-kyc',  [MerchantKycController::class, 'reuploadVideoKyc']);
//     Route::post('/merchant/reupload/digilocker', [MerchantKycController::class, 'reuploadDigilocker']);
// });

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [AuthenticatedSessionController::class, 'me']);

    Route::get('/merchant-kyc-documents/{user_id}', [MerchantKycController::class, 'listDocuments']);
    Route::post('/merchant/reupload/document',      [MerchantKycController::class, 'reuploadDocument']);
    Route::post('/merchant/reupload/video-kyc',     [MerchantKycController::class, 'reuploadVideoKyc']);
    Route::post('/merchant/reupload/digilocker',    [MerchantKycController::class, 'reuploadDigilocker']);
    Route::post('/document-approve',                [MerchantKycController::class, 'documentApprove']);
    Route::post('/document-reject',                 [MerchantKycController::class, 'documentReject']);
});

Route::post('/merchant/kyc/save-step',[MerchantKycDraftController::class,'saveStep']);
Route::post('/merchant/kyc/details',  [MerchantKycDraftController::class,'getDetails']);



