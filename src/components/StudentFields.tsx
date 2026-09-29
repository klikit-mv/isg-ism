'use client';

import { Gender, ScoutSection } from '@/lib/enums';
import { Input, Select } from './form/fields';

export interface StudentDefaults {
  name?: string | null;
  indexNumber?: string | null;
  nationalId?: string | null;
  email?: string | null;
  gender?: string | null;
  dateOfBirth?: string | null;
  section?: string | null;
  parentName?: string | null;
  primaryMobile?: string | null;
  secondaryMobile?: string | null;
  permanentAddress?: string | null;
  presentAddress?: string | null;
}

/** Scout fields shared by public registration and enrolment. */
export function StudentFields({ student = {} }: { student?: StudentDefaults }) {
  return (
    <div className="grid gap-4 sm:grid-cols-2">
      <Input name="name" label="Full name" defaultValue={student.name} required />
      <Input name="index_number" label="Index number" defaultValue={student.indexNumber} required />
      <Input name="national_id" label="National ID" defaultValue={student.nationalId} required className="input uppercase" />
      <Input name="email" label="Email" type="email" defaultValue={student.email} required />
      <Select name="gender" label="Gender" options={Gender.options()} defaultValue={student.gender} placeholder="Choose" required />
      <Input name="date_of_birth" label="Date of birth" type="date" defaultValue={student.dateOfBirth} required />
      <Select name="section" label="Section" options={ScoutSection.options()} defaultValue={student.section} placeholder="Choose" required />
      <Input name="parent_name" label="Parent name" defaultValue={student.parentName} required />
      <Input name="primary_mobile" label="Primary mobile" defaultValue={student.primaryMobile} required />
      <Input name="secondary_mobile" label="Secondary mobile (optional)" defaultValue={student.secondaryMobile} />
      <Input name="permanent_address" label="Permanent address" defaultValue={student.permanentAddress} required />
      <Input name="present_address" label="Present address" defaultValue={student.presentAddress} required />
    </div>
  );
}
