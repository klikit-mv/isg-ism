import { mediaUrl } from '@/server/media';
import { Avatar } from './Avatar';

export function StudentAvatar({ student, className = 'h-8 w-8' }: { student: { name: string; photoPath: string | null }; className?: string }) {
  return <Avatar name={student.name} url={mediaUrl(student.photoPath)} className={className} />;
}
