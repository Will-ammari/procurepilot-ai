<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Organization;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoProcurePilotSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::updateOrCreate(
            ['name' => 'Berlin Mittelstand GmbH'],
            [
                'country' => 'DE',
                'currency' => 'EUR',
                'vat_rate' => 19.00,
            ]
        );

        $engineering = Department::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'Engineering',
            ],
            ['code' => 'ENG']
        );

        $operations = Department::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'Operations',
            ],
            ['code' => 'OPS']
        );

        $finance = Department::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'Finance',
            ],
            ['code' => 'FIN']
        );

        $procurement = Department::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'Procurement',
            ],
            ['code' => 'PROC']
        );

        $password = Hash::make('password');

        User::updateOrCreate(
            ['email' => 'admin@procurepilot.test'],
            [
                'organization_id' => $organization->id,
                'department_id' => $operations->id,
                'name' => 'Anna Müller',
                'password' => $password,
                'role' => User::ROLE_ADMIN,
            ]
        );

        User::updateOrCreate(
            ['email' => 'requester@procurepilot.test'],
            [
                'organization_id' => $organization->id,
                'department_id' => $engineering->id,
                'name' => 'Jonas Weber',
                'password' => $password,
                'role' => User::ROLE_REQUESTER,
            ]
        );

        User::updateOrCreate(
            ['email' => 'procurement@procurepilot.test'],
            [
                'organization_id' => $organization->id,
                'department_id' => $procurement->id,
                'name' => 'Sophie Schneider',
                'password' => $password,
                'role' => User::ROLE_PROCUREMENT,
            ]
        );

        User::updateOrCreate(
            ['email' => 'manager@procurepilot.test'],
            [
                'organization_id' => $organization->id,
                'department_id' => $engineering->id,
                'name' => 'Markus Fischer',
                'password' => $password,
                'role' => User::ROLE_MANAGER,
            ]
        );

        User::updateOrCreate(
            ['email' => 'finance@procurepilot.test'],
            [
                'organization_id' => $organization->id,
                'department_id' => $finance->id,
                'name' => 'Laura Becker',
                'password' => $password,
                'role' => User::ROLE_FINANCE,
            ]
        );

        User::updateOrCreate(
            ['email' => 'viewer@procurepilot.test'],
            [
                'organization_id' => $organization->id,
                'department_id' => $operations->id,
                'name' => 'Felix Wagner',
                'password' => $password,
                'role' => User::ROLE_VIEWER,
            ]
        );

        $vendors = [
            [
                'name' => 'Müller Office GmbH',
                'legal_name' => 'Müller Office GmbH',
                'vat_id' => 'DE123456789',
                'email' => 'sales@mueller-office.test',
                'phone' => '+49 30 1000001',
                'website' => 'https://mueller-office.test',
                'address' => 'Alexanderplatz 1, 10178 Berlin',
                'country' => 'DE',
                'default_currency' => 'EUR',
                'status' => Vendor::STATUS_ACTIVE,
                'contact_name' => 'Klara Müller',
            ],
            [
                'name' => 'Schneider Bürobedarf GmbH',
                'legal_name' => 'Schneider Bürobedarf GmbH',
                'vat_id' => 'DE987654321',
                'email' => 'angebote@schneider-buero.test',
                'phone' => '+49 30 1000002',
                'website' => 'https://schneider-buero.test',
                'address' => 'Friedrichstraße 20, 10117 Berlin',
                'country' => 'DE',
                'default_currency' => 'EUR',
                'status' => Vendor::STATUS_ACTIVE,
                'contact_name' => 'Nina Schneider',
            ],
            [
                'name' => 'BerlinTech Supplies GmbH',
                'legal_name' => 'BerlinTech Supplies GmbH',
                'vat_id' => 'DE112233445',
                'email' => 'procurement@berlintech-supplies.test',
                'phone' => '+49 30 1000003',
                'website' => 'https://berlintech-supplies.test',
                'address' => 'Invalidenstraße 55, 10557 Berlin',
                'country' => 'DE',
                'default_currency' => 'EUR',
                'status' => Vendor::STATUS_ACTIVE,
                'contact_name' => 'Tobias Klein',
            ],
            [
                'name' => 'Hamburg Industrial Systems GmbH',
                'legal_name' => 'Hamburg Industrial Systems GmbH',
                'vat_id' => 'DE556677889',
                'email' => 'sales@hamburg-industrial.test',
                'phone' => '+49 40 2000001',
                'website' => 'https://hamburg-industrial.test',
                'address' => 'Hafenstraße 10, 20457 Hamburg',
                'country' => 'DE',
                'default_currency' => 'EUR',
                'status' => Vendor::STATUS_ACTIVE,
                'contact_name' => 'Martin Schulz',
            ],
            [
                'name' => 'CloudWerk Software GmbH',
                'legal_name' => 'CloudWerk Software GmbH',
                'vat_id' => 'DE667788990',
                'email' => 'renewals@cloudwerk.test',
                'phone' => '+49 89 3000001',
                'website' => 'https://cloudwerk.test',
                'address' => 'Leopoldstraße 80, 80802 München',
                'country' => 'DE',
                'default_currency' => 'EUR',
                'status' => Vendor::STATUS_ACTIVE,
                'contact_name' => 'Julia Becker',
            ],
        ];

        foreach ($vendors as $vendorData) {
            $contactName = $vendorData['contact_name'];
            unset($vendorData['contact_name']);

            $vendor = Vendor::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => $vendorData['name'],
                ],
                array_merge($vendorData, [
                    'organization_id' => $organization->id,
                ])
            );

            VendorContact::updateOrCreate(
                [
                    'vendor_id' => $vendor->id,
                    'email' => $vendor->email,
                ],
                [
                    'name' => $contactName,
                    'phone' => $vendor->phone,
                    'role' => 'Sales Representative',
                ]
            );
        }
    }
}
