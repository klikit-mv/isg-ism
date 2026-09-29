import { studentPhotoAction } from '@/app/actions/students';
import { ActionButton } from './ConfirmButton';
import { PhotoUpload } from './PhotoUpload';

export function PhotoForm({ uuid, hasPhoto }: { uuid: string; hasPhoto: boolean }) {
  return (
    <div className="card space-y-3">
      <h2 className="font-semibold">Photo</h2>
      <PhotoUpload action={studentPhotoAction} fields={{ uuid }} />
      {hasPhoto && <ActionButton action={studentPhotoAction} label="Remove photo" fields={{ uuid, remove: '1' }} />}
    </div>
  );
}
