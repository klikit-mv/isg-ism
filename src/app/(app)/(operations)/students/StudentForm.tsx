'use client';

import Link from 'next/link';
import { createStudentAction, updateStudentAction } from '@/app/actions/students';
import { ActionForm, SubmitButton } from '@/components/form/ActionForm';
import { Checkbox, FileInput, Input, Select } from '@/components/form/fields';
import { StudentFields, type StudentDefaults } from '@/components/StudentFields';
import { StudentStatus } from '@/lib/enums';

export function StudentForm({ student }: { student?: (StudentDefaults & { uuid: string; className: string | null; patrol: string | null; status: string; photoPath: string | null }) | null }) {
  const editing = !!student;
  return (
    <ActionForm action={editing ? updateStudentAction : createStudentAction} className="card space-y-6" encType="multipart/form-data">
      {editing && <input type="hidden" name="uuid" value={student.uuid} />}
      <StudentFields student={student ?? {}} />
      <div className="grid gap-4 sm:grid-cols-3">
        <Input name="class_name" label="Class" defaultValue={student?.className} />
        <Input name="patrol" label="Patrol" defaultValue={student?.patrol} />
        <Select name="status" label="Status" options={StudentStatus.options()} defaultValue={student?.status ?? 'active'} />
      </div>
      <div className="grid gap-4 sm:grid-cols-2">
        {!editing && <Input name="pin" label="PIN (optional)" type="password" help="Leave blank to generate a temporary 4-digit PIN." />}
        <div>
          <FileInput name="photo" label="Photo (optional)" accept="image/png,image/jpeg,image/webp" help="PNG, JPEG or WebP, up to 5 MB." />
          {editing && student.photoPath && <div className="mt-2"><Checkbox name="remove_photo" label="Remove the current photo" /></div>}
        </div>
      </div>
      <div className="flex gap-2">
        <SubmitButton>{editing ? 'Save changes' : 'Enrol scout'}</SubmitButton>
        <Link href={editing ? `/students/${student.uuid}` : '/students'} className="btn-secondary">Cancel</Link>
      </div>
    </ActionForm>
  );
}
