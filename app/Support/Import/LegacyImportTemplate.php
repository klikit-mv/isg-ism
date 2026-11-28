<?php

namespace App\Support\Import;

use App\Enums\AttendanceStatus;
use App\Enums\FeeStatus;
use App\Enums\Gender;
use App\Enums\ParentLinkStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Enums\PersonType;
use App\Enums\PurchaseStatus;
use App\Enums\RecordStatus;
use App\Enums\Role;
use App\Enums\RoverAttendanceStatus;
use App\Enums\ScoutSection;
use App\Enums\ShopItemStatus;
use App\Enums\StudentStatus;
use App\Enums\UserStatus;

/**
 * A sample legacy workbook: every importable sheet with its columns, an
 * example row and dropdown lists for fixed-choice columns.
 */
final class LegacyImportTemplate
{
    public static function build(): string
    {
        $yesNo = ['yes', 'no'];

        return (new TemplateBuilder)
            ->sheet('Students', ['ID', 'Index Number', 'Name', 'National ID', 'Email', 'Gender', 'Section', 'Status', 'Date of Birth', 'Parent Name', 'Primary Mobile', 'Secondary Mobile', 'Permanent Address', 'Present Address', 'Class Name', 'Patrol'],
                [['S1', 'IX001', 'Ali Example', 'A1111111', 'ali@example.com', 'Male', 'Scout', 'active', '01.02.2012', 'Hassan Example', '7771234', '', 'Male', 'Male', 'Grade 7', 'Eagle']],
                ['Gender' => Gender::values(), 'Section' => ScoutSection::values(), 'Status' => StudentStatus::values()])
            ->sheet('Users', ['ID', 'Name', 'National ID', 'Email', 'Role', 'Status', 'PIN', 'PIN Salt', 'Student ID'],
                [['U1', 'Leader Example', 'A9000001', 'leader@example.com', 'leader', 'active', '1234', '', ''], ['U2', 'Parent Example', 'A9000002', 'parent@example.com', 'parent', 'active', '4321', '', '']],
                ['Role' => Role::values(), 'Status' => UserStatus::values()])
            ->sheet('ParentLinks', ['Parent ID', 'Student ID', 'Status'], [['U2', 'S1', 'approved']], ['Status' => ParentLinkStatus::values()])
            ->sheet('Groups', ['ID', 'Name', 'Type', 'Status'], [['G1', 'Eagle Patrol', 'Patrol', 'Active']], ['Status' => RecordStatus::values()])
            ->sheet('GroupMembers', ['Group ID', 'Student ID'], [['G1', 'S1']])
            ->sheet('GroupLeaders', ['Group ID', 'User ID'], [['G1', 'U1']])
            ->sheet('GroupAssistantLeaders', ['Group ID', 'Student ID'], [])
            ->sheet('Activities', ['ID', 'Name', 'Date', 'Details', 'All Students', 'Sections', 'Groups', 'Charge Fee', 'Fee Amount'],
                [['A1', 'Camp Night', '12.03.2026 18:30', '', 'no', 'Scout', 'G1', 'yes', '20']],
                ['All Students' => $yesNo, 'Charge Fee' => $yesNo])
            ->sheet('Attendance', ['Activity ID', 'Student ID', 'Status', 'Remarks'], [['A1', 'S1', 'Present', '']], ['Status' => AttendanceStatus::values()])
            ->sheet('RoverAttendance', ['Activity ID', 'Student ID', 'Status', 'Is Required'], [], ['Status' => RoverAttendanceStatus::values(), 'Is Required' => $yesNo])
            ->sheet('ClassFeeConfig', ['Activity ID', 'Amount'], [['A1', '20']])
            ->sheet('ClassFees', ['ID', 'Activity ID', 'Student ID', 'Amount', 'Status', 'Due Date'], [['CF1', 'A1', 'S1', '20', 'Pending', '26.03.2026']], ['Status' => FeeStatus::values()])
            ->sheet('Configuration', ['Key', 'Value'], [['bank_name', 'Bank of Maldives']],
                ['Key' => ['default_class_fee', 'shop_enabled', 'proof_max_kb', 'bank_name', 'account_name', 'account_number', 'payment_instructions', 'footer_text']])
            ->sheet('UserPermissions', ['User ID', 'Permission'], [['U1', 'canVerifyPayments']], ['Permission' => Permission::values()])
            ->sheet('AnnualFeeConfig', ['Year', 'Amount', 'Status'], [['2026', '150', 'Active']], ['Status' => RecordStatus::values()])
            ->sheet('AnnualFees', ['ID', 'Year', 'Person Type', 'Student ID', 'User ID', 'Section', 'Amount'], [['AF1', '2026', 'Student', 'S1', '', 'Scout', '150']],
                ['Person Type' => PersonType::values(), 'Section' => ScoutSection::values()])
            ->sheet('Payments', ['ID', 'Type', 'Payable ID', 'Amount', 'Method', 'Status', 'Submitted At', 'Verified At', 'Rejection Reason'],
                [['P1', 'Class Fee', 'CF1', '20', 'cash', 'Paid', '12.03.2026', '12.03.2026', '']],
                ['Type' => ['Class Fee', 'Annual Fee', 'Purchase'], 'Method' => PaymentMethod::values(), 'Status' => PaymentStatus::values()])
            ->sheet('ShopItems', ['ID', 'Name', 'Description', 'Price', 'Stock', 'Status'], [['I1', 'Scarf', 'Group scarf', '85', '10', 'Active']], ['Status' => ShopItemStatus::values()])
            ->sheet('Purchases', ['ID', 'Student ID', 'Item ID', 'Quantity', 'Unit Price', 'Total', 'Purchase Status'], [['PU1', 'S1', 'I1', '1', '85', '85', 'PendingPayment']],
                ['Purchase Status' => PurchaseStatus::values()])
            ->save();
    }
}
