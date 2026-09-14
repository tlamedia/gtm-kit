/**
 * Covers dismissing toasts and how they are announced: an error toast is an
 * alert with a close control when the container can dismiss it.
 */

/* eslint-disable import/no-extraneous-dependencies */

import { render, screen, fireEvent } from '@testing-library/react';

import { ToastContainer } from '../Toast';

describe( 'ToastContainer', () => {
	it( 'announces an error toast as an alert and dismisses it by id', () => {
		const onDismiss = jest.fn();

		render(
			<ToastContainer
				toasts={ [
					{
						id: 7,
						message: 'Save failed',
						type: 'error',
						duration: 0,
					},
				] }
				onDismiss={ onDismiss }
			/>
		);

		expect( screen.getByRole( 'alert' ).textContent ).toContain(
			'Save failed'
		);

		fireEvent.click( screen.getByLabelText( 'Close' ) );
		expect( onDismiss ).toHaveBeenCalledWith( 7 );
	} );

	it( 'announces other toasts as status and offers no close control without a dismiss handler', () => {
		render(
			<ToastContainer
				toasts={ [
					{ id: 1, message: 'Saved', type: 'success', duration: 0 },
				] }
			/>
		);

		expect( screen.getByRole( 'status' ).textContent ).toContain( 'Saved' );
		expect( screen.queryByLabelText( 'Close' ) ).toBeNull();
	} );
} );
