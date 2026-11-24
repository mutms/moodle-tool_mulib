import {formatDate} from '../../src/muform/datetimeapi';

describe('tool_mulib/muform/datetimeapi', () => {
    it('formats dates in the timezone of the value', () => {
        const timestamp = Date.UTC(2026, 10, 30, 23, 30) / 1000;
        expect(formatDate(timestamp, 'Europe/Prague', 'en', 'Y-m-d')).toBe('2026-12-01');
        expect(formatDate(timestamp, 'America/New_York', 'en', 'Y-m-d')).toBe('2026-11-30');
        expect(formatDate(timestamp, 'Europe/Prague', 'en', 'j. n. Y')).toBe('1. 12. 2026');
        expect(formatDate(timestamp, 'Europe/Prague', 'en', 'n/j/y')).toBe('12/1/26');
        expect(formatDate(timestamp, 'Europe/Prague', 'en', 'F j, Y')).toBe('December 1, 2026');
        expect(formatDate(timestamp, 'Europe/Prague', 'en', 'j. \\h\\o\\d. M')).toBe('1. hod. Dec');
    });
});
