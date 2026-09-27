<?php

namespace Tests\Feature\Import;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<list<string>>  $rows
     */
    private function workbook(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows);
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'students.xlsx', null, null, true);
    }

    /**
     * @return list<string>
     */
    private function row(string $name, string $nationalId, string $email, string $section = 'cub scout'): array
    {
        return [$name, $nationalId, $email, 'female', $section, 'IX'.substr($nationalId, 1), 'Addu', 'Male', '15.03.2015', 'Parent', '7771234', '', '', '', 'active', ''];
    }

    public function test_template_has_the_documented_headers_and_an_example(): void
    {
        $response = $this->actingAs($this->admin())->get('/students/import/template');

        $response->assertOk();
        $rows = IOFactory::load($response->getFile()->getPathname())->getActiveSheet()->toArray();
        $this->assertSame(['name', 'national_id', 'email'], array_slice($rows[0], 0, 3));
        $this->assertCount(2, $rows);
    }

    public function test_preview_writes_nothing_and_confirm_imports_ready_rows(): void
    {
        Storage::fake('local');
        Student::factory()->create(['national_id' => 'A0000002']);
        $headers = ['name', 'national_id', 'email', 'gender', 'section', 'index_number', 'permanent_address', 'present_address', 'date_of_birth', 'parent_name', 'primary_mobile', 'secondary_mobile', 'class_name', 'patrol', 'status', 'pin'];
        $file = $this->workbook([
            $headers,
            $this->row('New Scout', 'a0000001', 'new@example.com'),
            $this->row('Existing', 'A0000002', 'exists@example.com'),
            $this->row('Twin One', 'A0000003', 'twin@example.com'),
            $this->row('Twin Two', 'A0000004', 'twin@example.com'),
            $this->row('Bad Section', 'A0000005', 'bad@example.com', 'astronaut'),
        ]);
        $leader = $this->leader();

        $this->actingAs($leader)->post('/students/import/preview', ['file' => $file])->assertRedirect(route('students.import'));
        $report = session('student_import_report');

        $this->assertSame(1, Student::query()->count());
        $this->assertSame(['ready', 'exists', 'error', 'error', 'error'], array_column($report['rows'], 'result'));
        $this->assertCount(1, Storage::disk('local')->files('imports'));

        $this->actingAs($leader)->post('/students/import/confirm')->assertSessionHas('success', '1 scout(s) enrolled, 1 skipped, 3 with errors.');

        $imported = Student::query()->where('national_id', 'A0000001')->firstOrFail();
        $this->assertSame('Cub Scout', $imported->section->value);
        $this->assertSame('2015-03-15', $imported->date_of_birth->toDateString());
        $this->assertTrue($imported->user->hasRole('student'));
        $this->assertSame([], Storage::disk('local')->files('imports'));
    }

    public function test_file_without_required_headers_is_reported(): void
    {
        Storage::fake('local');

        $this->actingAs($this->admin())->post('/students/import/preview', ['file' => $this->workbook([['name', 'section'], ['X', 'Scout']])]);

        $this->assertSame(['nationalid', 'email'], session('student_import_report')['missing']);
    }

    public function test_parents_cannot_import(): void
    {
        $this->actingAs($this->parentOf())->get('/students/import')->assertForbidden();
    }
}
