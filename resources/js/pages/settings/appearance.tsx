import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

export default function Appearance() {
    return (
        <>
            <Head title="Appearance settings" />

            <h1 className="sr-only">Appearance settings</h1>

            <Card className="rounded-none border-border shadow-none">
                <CardHeader>
                    <CardTitle>Appearance settings</CardTitle>
                    <CardDescription>
                        Update the appearance settings for your account
                    </CardDescription>
                </CardHeader>

                <CardContent>
                    <AppearanceTabs />
                </CardContent>
            </Card>
        </>
    );
}
