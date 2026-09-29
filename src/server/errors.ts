/** A business rule was broken. Shown to the person as a plain message. */
export class ScoutError extends Error {
  constructor(message: string, readonly field?: string) {
    super(message);
    this.name = 'ScoutError';
  }
}

/** The person may not see or change this record. */
export class NotAccessibleError extends ScoutError {
  constructor(message = 'You do not have access to this record.') {
    super(message);
    this.name = 'NotAccessibleError';
  }
}

/** Validation failed; `fields` maps input names to messages. */
export class ValidationError extends ScoutError {
  constructor(readonly fields: Record<string, string>, message = 'Please check the form.') {
    super(message);
    this.name = 'ValidationError';
  }
}
